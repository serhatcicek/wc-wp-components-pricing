<?php
function display_product_components_with_defaults() {
    // WooCommerce ve ACF eklentilerinin aktif olup olmadığını kontrol et
    if (!function_exists('wc_get_product') || !function_exists('get_field')) {
        return; // Gerekli eklentiler yüklü değilse çalışma
    }
    
    // WooCommerce global product nesnesini al
    if (!is_product()) {
        return; // Sadece ürün sayfalarında çalışsın
    }

    global $product;

    // Ürün nesnesi yoksa işlemi durdur
    if ( ! $product ) {
        return;
    }

    // Varsayılan bileşenleri ve eklenebilir bileşenleri al
    $default_components = get_field('_default_components', $product->get_id());
    $additional_components = get_field('_product_components', $product->get_id());

    if ($default_components || $additional_components) {
        echo '<table class="table product-feature cart-details components-edit-table note-head">';
        echo '<thead><tr><th>Parçalar</th><th>Birim Fiyat</th><th>Adet</th><th>Toplam Fiyat</th></tr></thead>';
        echo '<tbody>';

        $total_price = 0;

        // Varsayılan bileşenler
        if (!empty($default_components)) {
            foreach (explode(',', $default_components) as $component_id) {
                $component_product = wc_get_product($component_id);
                if (!$component_product) continue; // Geçersiz ürün ID'si kontrolü
                
                $price = $component_product->get_price();
                $quantity = 1; // Varsayılan adet
                $total_price += $price * $quantity;
                echo '<tr>';
                echo '<td>' . esc_html($component_product->get_name()) . '</td>';
                echo '<td>' . wc_price($price) . '</td>';
                echo '<td><input type="number" class="component-quantity" data-component-id="' . esc_attr($component_id) . '" value="' . esc_attr($quantity) . '" min="0" max="9"></td>';
                echo '<td class="component-total-price" data-price="' . esc_attr($price) . '">' . wc_price($price * $quantity) . '</td>';
                echo '</tr>';
            }
        }

        // Eklenebilir bileşenler
        if (!empty($additional_components)) {
            foreach (explode(',', $additional_components) as $component_id) {
                $component_product = wc_get_product($component_id);
                if (!$component_product) continue; // Geçersiz ürün ID'si kontrolü
                
                $price = $component_product->get_price();
                $quantity = 0; // Ek bileşenler varsayılan 0 adet
                echo '<tr>';
                echo '<td>' . esc_html($component_product->get_name()) . '</td>';
                echo '<td>' . wc_price($price) . '</td>';
                echo '<td><input type="number" class="component-quantity" data-component-id="' . esc_attr($component_id) . '" value="' . esc_attr($quantity) . '" min="0" max="9"></td>';
                echo '<td class="component-total-price" data-price="' . esc_attr($price) . '">' . wc_price($price * $quantity) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';

        // Toplam fiyatı ekrana yazdır
        echo '<p>Toplam Fiyat: <span id="total_price">' . wc_price($total_price) . '</span></p>';

        // Özel fiyat alanını ekle (hidden field)
        echo '<input type="hidden" name="custom_price" value="' . esc_attr($total_price) . '">';
        
        // Seçilen bileşenleri depolamak için hidden field
        echo '<input type="hidden" name="selected_components" value="">';

        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.component-quantity').on('change', function() {
                let componentId = $(this).data('component-id');
                let quantity = parseInt($(this).val());
                let price = parseFloat($(this).closest('tr').find('.component-total-price').data('price'));
                let totalPriceElement = $(this).closest('tr').find('.component-total-price');

                let totalPrice = price * quantity;
                // wc_price formatında güncelle
                totalPriceElement.html(formatPrice(totalPrice));

                updateTotalPrice();
                updateSelectedComponents();
            });

            function formatPrice(price) {
                // WooCommerce fiyat formatını kullanacak şekilde basit bir formatlama
                return '₺' + price.toFixed(2).replace('.', ',');
            }

            function updateTotalPrice() {
                let totalPrice = 0;
                $('.component-total-price').each(function() {
                    // Veri özniteliğinden fiyatı al
                    let price = parseFloat($(this).data('price'));
                    // İlgili satırın miktar değerini al
                    let quantity = parseInt($(this).closest('tr').find('.component-quantity').val());
                    totalPrice += price * quantity;
                });

                // Toplam fiyatı güncelle
                $('#total_price').html(formatPrice(totalPrice));

                // WooCommerce ürün fiyatını güncelle (eğer varsa)
                if ($('.woocommerce-Price-amount.amount').length) {
                    $('.woocommerce-Price-amount.amount bdi').html(formatPrice(totalPrice));
                }

                // Hidden input'a toplam fiyatı ekle
                $('input[name="custom_price"]').val(totalPrice);
            }
            
            function updateSelectedComponents() {
                let selectedComponents = {};
                $('.component-quantity').each(function() {
                    let componentId = $(this).data('component-id');
                    let quantity = parseInt($(this).val());
                    
                    if (quantity > 0) {
                        selectedComponents[componentId] = quantity;
                    }
                });
                
                $('input[name="selected_components"]').val(JSON.stringify(selectedComponents));
            }
            
            // Sayfa yüklendiğinde fiyatları başlangıç olarak güncelle
            updateTotalPrice();
            updateSelectedComponents();
        });
        </script>
        <?php
    }
}
add_shortcode('display_components', 'display_product_components_with_defaults');

/**
 * Sepete ekleme işlemi sırasında seçilen bileşenleri ekle
 */
function add_components_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Özel fiyat ve seçilen bileşenler gönderilmiş mi kontrol et
    if (!isset($_POST['custom_price']) || !isset($_POST['selected_components']) || empty($_POST['selected_components'])) {
        return;
    }
    
    $selected_components = json_decode(stripslashes($_POST['selected_components']), true);
    
    if (!is_array($selected_components) || empty($selected_components)) {
        return;
    }
    
    // Cart item data'ya bileşenleri ekle
    WC()->cart->cart_contents[$cart_item_key]['component_data'] = $selected_components;
    
    // Özel fiyat ayarla (eğer gerekirse)
    if (isset($_POST['custom_price']) && is_numeric($_POST['custom_price'])) {
        WC()->cart->cart_contents[$cart_item_key]['custom_price'] = floatval($_POST['custom_price']);
    }
}
add_action('woocommerce_add_to_cart', 'add_components_to_cart', 10, 6);

/**
 * Sepet fiyatını özel fiyatla güncelle
 */
function update_cart_item_price($cart_obj) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    foreach ($cart_obj->get_cart() as $key => $cart_item) {
        if (isset($cart_item['custom_price'])) {
            $cart_item['data']->set_price($cart_item['custom_price']);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'update_cart_item_price', 10, 1);

/**
 * Sepet satırında bileşenleri göster
 */
function display_components_in_cart($item_data, $cart_item) {
    if (isset($cart_item['component_data']) && !empty($cart_item['component_data'])) {
        $item_data[] = array(
            'key'   => 'Bileşenler',
            'value' => format_component_list($cart_item['component_data']),
        );
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'display_components_in_cart', 10, 2);

/**
 * Bileşen listesini formatla
 */
function format_component_list($component_data) {
    $output = '<ul class="component-list">';
    foreach ($component_data as $component_id => $quantity) {
        $component = wc_get_product($component_id);
        if ($component) {
            $output .= '<li>' . esc_html($component->get_name()) . ' x ' . esc_html($quantity) . '</li>';
        }
    }
    $output .= '</ul>';
    return $output;
}
