<?php
namespace WCDCP;

if (!defined('ABSPATH')) exit;

class Frontend {

  public static function init(){
    add_shortcode('display_components', [__CLASS__, 'shortcode']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
  }

  public static function assets(){
    wp_enqueue_style('wcdcp-style', WCDCP_URL.'assets/style.css', [], '1.0.0');
  }

  public static function shortcode(){
    if (!is_product()) return '';
    global $product;
    if (!$product) return '';

    // ACF fields: comma-separated product IDs
    $default = function_exists('get_field') ? get_field('_default_components', $product->get_id()) : '';
    $extra   = function_exists('get_field') ? get_field('_product_components', $product->get_id()) : '';

    $rows = [];

    // default components (qty = 1)
    if (!empty($default)) {
      foreach (explode(',', $default) as $id) {
        $p = wc_get_product(trim($id)); if(!$p) continue;
        $rows[] = [
          'name' => $p->get_name(),
          'price'=> (float)$p->get_price(),
          'qty'  => 1,
        ];
      }
    }
    // additional components (qty = 0)
    if (!empty($extra)) {
      foreach (explode(',', $extra) as $id) {
        $p = wc_get_product(trim($id)); if(!$p) continue;
        $rows[] = [
          'name' => $p->get_name(),
          'price'=> (float)$p->get_price(),
          'qty'  => 0,
        ];
      }
    }

    if (empty($rows)) return '';

    // initial total
    $initial_total = 0;
    foreach($rows as $r){ $initial_total += $r['price'] * $r['qty']; }

    ob_start(); ?>
    <div class="wcdcp">
      <table class="wcdcp-table">
        <thead><tr>
          <th>Component</th><th>Unit Price</th><th>Quantity</th><th>Total</th>
        </tr></thead>
        <tbody>
          <?php foreach($rows as $i => $r): ?>
            <tr>
              <td><?php echo esc_html($r['name']); ?></td>
              <td class="wcdcp-unit"><?php echo wc_price($r['price']); ?></td>
              <td>
                <input type="number" class="wcdcp-qty" data-price="<?php echo esc_attr($r['price']); ?>"
                       value="<?php echo esc_attr($r['qty']); ?>" min="0" max="9">
              </td>
              <td class="wcdcp-rowtotal"><?php echo wc_price($r['price'] * $r['qty']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <p class="wcdcp-grand">Grand Total:
        <span id="wcdcp_total"><?php echo wc_price($initial_total); ?></span>
      </p>

      <!-- pass to cart -->
      <input type="hidden" name="custom_price" id="wcdcp_custom_price" value="<?php echo esc_attr($initial_total); ?>">
    </div>

    <script>
    (function(){
      const fmt = new Intl.NumberFormat('<?php echo esc_js( get_locale() === 'tr_TR' ? 'tr-TR' : 'en-US' ); ?>', {
        style:'currency', currency:'<?php echo esc_js( get_woocommerce_currency() ); ?>'
      });

      const qties = document.querySelectorAll('.wcdcp .wcdcp-qty');
      const totalEl = document.getElementById('wcdcp_total');
      const hidden  = document.getElementById('wcdcp_custom_price');

      function rowTotal(price, qty){ return price * Math.max(0, parseInt(qty||0)); }

      function recalc(){
        let grand = 0;
        document.querySelectorAll('.wcdcp tbody tr').forEach(function(tr){
          const input = tr.querySelector('.wcdcp-qty');
          const price = parseFloat(input.dataset.price||'0');
          const qty   = parseInt(input.value||'0');
          const rt    = rowTotal(price, qty);
          tr.querySelector('.wcdcp-rowtotal').textContent = fmt.format(rt);
          grand += rt;
        });
        totalEl.textContent = fmt.format(grand);
        if (hidden) hidden.value = grand;

        // also update product price display
        const priceAmount = document.querySelector('.summary .price .amount bdi, .summary .price .woocommerce-Price-amount');
        if (priceAmount){ priceAmount.textContent = fmt.format(grand); }
      }

      qties.forEach(function(i){ i.addEventListener('change', recalc); });
    })();
    </script>
    <?php
    return ob_get_clean();
  }
}
