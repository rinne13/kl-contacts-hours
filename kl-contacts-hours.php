<?php
/**
 * Plugin Name: KL – Contacts & Hours
 * Description: Контакты, соцсети и часы работы + шорткоды: [kl_contact_page], [kl_contact_buttons], [kl_hours].
 * Version:     1.0.0
 * Author:      Varvara B.
 * Text Domain: koiranloma
 */

if (!defined('ABSPATH')) exit;

define('KL_CH_OPT', 'kl_contacts_hours');
define('KL_CH_URL', plugin_dir_url(__FILE__));
define('KL_CH_DIR', plugin_dir_path(__FILE__));

final class KL_Contacts_Hours {
  public function __construct() {
    add_action('admin_init',            [$this,'maybe_seed_defaults']);
    add_action('admin_menu',            [$this,'admin_menu']);
    add_action('admin_init',            [$this,'register_settings']);
    add_action('wp_enqueue_scripts',    [$this,'front_assets']);

    add_shortcode('kl_contact_buttons', [$this,'sc_buttons']);
    add_shortcode('kl_hours',           [$this,'sc_hours']);
    add_shortcode('kl_contact_page',    [$this,'sc_page']);
  }

  /** Дефолтные значения, если опции ещё нет */
  public function maybe_seed_defaults() {
    if (get_option(KL_CH_OPT) !== false) return;
    add_option(KL_CH_OPT, [
      'address'   => 'Helsinki, Suomi',
      'phone'     => '+358401234567',
      'email'     => 'info@example.com',
      'instagram' => '',
      'telegram'  => '',
      'whatsapp'  => '',     // если пусто — возьмём из phone
      'map_embed' => '',     // сюда можно вставить iframe карты
      'hours'     => [
        'mon' => ['10:00','20:00'],
        'tue' => ['10:00','20:00'],
        'wed' => ['10:00','20:00'],
        'thu' => ['10:00','20:00'],
        'fri' => ['10:00','20:00'],
        'sat' => ['11:00','18:00'],
        'sun' => ['Sopimuksen mukaan',''],
      ],
    ]);
  }

  /** Пункт меню «Yhteystiedot» */
  public function admin_menu() {
    add_menu_page(
      __('Yhteystiedot', 'koiranloma'),
      __('Yhteystiedot', 'koiranloma'),
      'manage_options',
      'kl-contacts-hours',
      [$this,'render_settings_page'],
      'dashicons-location-alt',
      27
    );
  }

  public function register_settings() {
    register_setting(KL_CH_OPT, KL_CH_OPT, [$this,'sanitize']);

    add_settings_section('klch_main', __('Asetukset', 'koiranloma'), function(){
      echo '<p>'.esc_html__('Täytä yhteystiedot, some-linkit ja aukioloajat.', 'koiranloma').'</p>';
    }, 'kl-contacts-hours');

    $fields = [
      'address'   => __('Osoite', 'koiranloma'),
      'phone'     => __('Puhelin (WhatsApp)', 'koiranloma'),
      'email'     => __('Sähköposti', 'koiranloma'),
      'instagram' => __('Instagram URL', 'koiranloma'),
      'telegram'  => __('Telegram käyttäjänimi (ilman @)', 'koiranloma'),
      'whatsapp'  => __('WhatsApp-numero (valinnainen)', 'koiranloma'),
      'map_embed' => __('Kartta (iframe upotus, valinnainen)', 'koiranloma'),
    ];

    foreach ($fields as $key=>$label) {
      add_settings_field($key, $label, function() use ($key) {
        $o = get_option(KL_CH_OPT);
        if ($key === 'map_embed') {
          printf('<textarea name="%s[%s]" rows="4" class="large-text code">%s</textarea>',
            esc_attr(KL_CH_OPT), esc_attr($key), esc_textarea($o[$key]??''));
          echo '<p class="description">'.esc_html__('Liitä esim. Google Maps upotus-iframe.', 'koiranloma').'</p>';
        } else {
          printf('<input type="text" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr(KL_CH_OPT), esc_attr($key), esc_attr($o[$key]??''));
        }
      }, 'kl-contacts-hours', 'klch_main');
    }

    add_settings_field('hours', __('Aukioloajat', 'koiranloma'), [$this,'hours_field'], 'kl-contacts-hours', 'klch_main');
  }

  /** Табличка часов работы */
  public function hours_field() {
    $o = get_option(KL_CH_OPT);
    $hours = is_array($o['hours'] ?? null) ? $o['hours'] : [];
    $days = [
      'mon'=>__('Ma','koiranloma'),'tue'=>__('Ti','koiranloma'),'wed'=>__('Ke','koiranloma'),
      'thu'=>__('To','koiranloma'),'fri'=>__('Pe','koiranloma'),'sat'=>__('La','koiranloma'),'sun'=>__('Su','koiranloma'),
    ];
    echo '<table class="widefat striped" style="max-width:640px">';
    echo '<thead><tr><th>'.esc_html__('Päivä','koiranloma').'</th><th>'.esc_html__('Auki alkaen','koiranloma').'</th><th>'.esc_html__('Auki asti','koiranloma').'</th></tr></thead><tbody>';
    foreach ($days as $k=>$label) {
      $from = $hours[$k][0] ?? '';
      $to   = $hours[$k][1] ?? '';
      echo '<tr>';
      echo '<td>'.$label.'</td>';
      printf('<td><input type="text" name="%s[hours][%s][0]" value="%s" placeholder="10:00 tai vapaa teksti" /></td>',
        esc_attr(KL_CH_OPT), esc_attr($k), esc_attr($from));
      printf('<td><input type="text" name="%s[hours][%s][1]" value="%s" placeholder="20:00" /></td>',
        esc_attr(KL_CH_OPT), esc_attr($k), esc_attr($to));
      echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p class="description">'.esc_html__('Jätä tyhjäksi, jos “sopimuksen mukaan” tai suljettu.', 'koiranloma').'</p>';
  }

// map settings:


  /** Санитайз */
  public function sanitize($in) {
  $out = [];
  $out['address']   = sanitize_text_field($in['address'] ?? '');
  $out['phone']     = sanitize_text_field($in['phone'] ?? '');
  $out['email']     = sanitize_email($in['email'] ?? '');
  $out['instagram'] = esc_url_raw($in['instagram'] ?? '');
  $out['telegram']  = sanitize_text_field($in['telegram'] ?? '');
  $out['whatsapp']  = sanitize_text_field($in['whatsapp'] ?? '');

  // allow <iframe> (карты)
  $allowed_iframe = [
    'iframe' => [
      'src'=>true,'width'=>true,'height'=>true,'style'=>true,'loading'=>true,
      'referrerpolicy'=>true,'allow'=>true,'allowfullscreen'=>true,'frameborder'=>true,
      'aria-label'=>true,'scrolling'=>true,'marginheight'=>true,'marginwidth'=>true,
    ]
  ];
  $out['map_embed'] = '';
  if (!empty($in['map_embed'])) {
    $out['map_embed'] = wp_kses($in['map_embed'], $allowed_iframe);
  }

  // hours
  $out['hours'] = [];
  if (!empty($in['hours']) && is_array($in['hours'])) {
    foreach ($in['hours'] as $k=>$row) {
      $from = isset($row[0]) ? sanitize_text_field($row[0]) : '';
      $to   = isset($row[1]) ? sanitize_text_field($row[1]) : '';
      $out['hours'][$k] = [$from, $to];
    }
  }
  return $out;
}

  
  /** Фронт стили */
  public function front_assets() {
    wp_enqueue_style('kl-contacts-hours', KL_CH_URL.'assets/style.css', [], '1.0.0');
  }

  /** Достаём структурированные контакты */
  public static function get_contacts() {
    $o  = get_option(KL_CH_OPT, []);
    $wa = $o['whatsapp'] ?: ($o['phone'] ?? '');
    $wa = preg_replace('/\D+/', '', $wa);

    return [
      'address'   => $o['address']   ?? '',
      'phone'     => $o['phone']     ?? '',
      'email'     => $o['email']     ?? '',
      'instagram' => $o['instagram'] ?? '',
      'telegram'  => $o['telegram']  ?? '',
      'whatsapp'  => $wa,
      'hours'     => $o['hours']     ?? [],
      'map_embed' => $o['map_embed'] ?? '',
    ];
  }

  /* ---------- ШОРТКОДЫ ---------- */

  /** Кнопки-быстрые контакты */
  public function sc_buttons() {
    $c  = self::get_contacts();
    $wa = $c['whatsapp'] ? 'https://wa.me/'.rawurlencode($c['whatsapp']) : '';
    $em = $c['email'] ? 'mailto:'.antispambot($c['email']) : '';
    $tg = $c['telegram'] ? 'https://t.me/'.rawurlencode($c['telegram']) : '';

    ob_start(); ?>
    <div class="kl-contacts-buttons">
      <?php if ($wa): ?><a class="btn-pill" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if ($tg): ?><a class="btn-pill" href="<?php echo esc_url($tg); ?>" target="_blank" rel="noopener">Telegram</a><?php endif; ?>
      <?php if ($em): ?><a class="btn-pill" href="<?php echo esc_url($em); ?>">Email</a><?php endif; ?>
      <?php if (!empty($c['instagram'])): ?><a class="btn-pill" href="<?php echo esc_url($c['instagram']); ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
    </div>
    <?php return ob_get_clean();
  }

  /** Таблица часов */
  public function sc_hours() {
    $c = self::get_contacts();
    $h = $c['hours'];
    if (!$h) return '';
    $labels = [
      'mon'=>__('Ma','koiranloma'),'tue'=>__('Ti','koiranloma'),'wed'=>__('Ke','koiranloma'),
      'thu'=>__('To','koiranloma'),'fri'=>__('Pe','koiranloma'),'sat'=>__('La','koiranloma'),'sun'=>__('Su','koiranloma'),
    ];
    ob_start(); ?>
    <div class="kl-hours">
      <table>
        <tbody>
          <?php foreach ($labels as $k=>$lab):
            $from = $h[$k][0] ?? '';
            $to   = $h[$k][1] ?? '';
            $val  = ($from && $to) ? "$from – $to" : ($from ?: __('Sopimuksen mukaan','koiranloma'));
          ?>
            <tr><th><?php echo esc_html($lab); ?></th><td><?php echo esc_html($val); ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php return ob_get_clean();
  }

  /** Полная секция контактов (для страницы /yhteystiedot/) */
  public function sc_page() {
    $c  = self::get_contacts();
    $wa = $c['whatsapp'] ? 'https://wa.me/'.rawurlencode($c['whatsapp']) : '';
    $em = $c['email'] ? 'mailto:'.antispambot($c['email']) : '';
    $tg = $c['telegram'] ? 'https://t.me/'.rawurlencode($c['telegram']) : '';

    ob_start(); ?>
    <section class="home-section kl-contact-page">
      <header class="home-section__header">
        <h2 class="home-section__title"><?php esc_html_e('Yhteystiedot', 'koiranloma'); ?></h2>
      </header>

      <div class="kl-contact-grid">
        <div class="kl-card">
          <h3><?php esc_html_e('Ota yhteyttä', 'koiranloma'); ?></h3>
          <ul class="kl-list">
            <?php if ($c['address']): ?><li><strong><?php esc_html_e('Osoite:', 'koiranloma'); ?></strong> <?php echo esc_html($c['address']); ?></li><?php endif; ?>
            <?php if ($c['phone']):   ?><li><strong><?php esc_html_e('Puhelin:', 'koiranloma'); ?></strong> +<?php echo esc_html($c['phone']); ?></li><?php endif; ?>
            <?php if ($c['email']):   ?><li><strong><?php esc_html_e('Sähköposti:', 'koiranloma'); ?></strong> <a href="<?php echo esc_url($em); ?>"><?php echo esc_html(antispambot($c['email'])); ?></a></li><?php endif; ?>
            <?php if ($c['instagram']): ?><li><strong>Instagram:</strong> <a href="<?php echo esc_url($c['instagram']); ?>" target="_blank" rel="noopener">@Instagram</a></li><?php endif; ?>
            <?php if ($c['telegram']):  ?><li><strong>Telegram:</strong> <a href="<?php echo esc_url($tg); ?>" target="_blank" rel="noopener">@<?php echo esc_html($c['telegram']); ?></a></li><?php endif; ?>
          </ul>
          <!-- <div class="kl-actions">
            <?php if ($wa): ?><a class="btn-cta" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener"><?php esc_html_e('WhatsApp', 'koiranloma'); ?></a><?php endif; ?>
            <?php echo do_shortcode('[kl_contact_buttons]'); ?>
          </div> -->
        </div>

        <div class="kl-card">
          <h3><?php esc_html_e('Aukioloajat', 'koiranloma'); ?></h3>
          <?php echo $this->sc_hours(); ?>
        </div>

       <!-- Col 3: Map -->
<div class="kl-card kl-card--wide">
  <h3><?php esc_html_e('Sijainti', 'koiranloma'); ?></h3>
  <?php if (!empty($c['map_embed'])): ?>
    <div class="kl-map-embed">
      <?php
        echo wp_kses($c['map_embed'], [
          'iframe' => [
            'src'=>true,'width'=>true,'height'=>true,'style'=>true,'loading'=>true,
            'referrerpolicy'=>true,'allow'=>true,'allowfullscreen'=>true,'frameborder'=>true,
            'aria-label'=>true,'scrolling'=>true,'marginheight'=>true,'marginwidth'=>true,
          ]
        ]);
      ?>
    </div>
  <?php elseif (!empty($c['address'])): ?>
    <p class="muted"><?php echo esc_html($c['address']); ?></p>
    <p class="muted"><?php esc_html_e('Lisää upotettu kartta asetuksissa (Google Maps tai OpenStreetMap).', 'koiranloma'); ?></p>
  <?php endif; ?>
</div>
        </section>
        <?php
        return ob_get_clean();
  }

  /** Страница настроек */
  public function render_settings_page() { ?>
    <div class="wrap">
      <h1><?php esc_html_e('Yhteystiedot & Aukioloajat', 'koiranloma'); ?></h1>
      <form method="post" action="options.php">
        <?php
          settings_fields(KL_CH_OPT);
          do_settings_sections('kl-contacts-hours');
          submit_button();
        ?>
      </form>
      <hr />
      <p><?php esc_html_e('Käytä koko lohkoa sivulla:', 'koiranloma'); ?> <code>[kl_contact_page]</code></p>
      <p><?php esc_html_e('Vain painikkeet:', 'koiranloma'); ?> <code>[kl_contact_buttons]</code></p>
      <p><?php esc_html_e('Vain aukioloajat:', 'koiranloma'); ?> <code>[kl_hours]</code></p>
    </div>
  <?php }
}

new KL_Contacts_Hours();

/* ====== ГЛАВНАЯ ТОЧКА ДАННЫХ ДЛЯ ТЕМЫ ====== */
if (!function_exists('kl_get_contacts')) {
  function kl_get_contacts() {
    return KL_Contacts_Hours::get_contacts();
  }
}

/* Совместимостьное имя: объявляем ТОЛЬКО если его нет (чтобы не конфликтовать с темой) */
if (!function_exists('koiranloma_get_contacts')) {
  function koiranloma_get_contacts() { return kl_get_contacts(); }
}