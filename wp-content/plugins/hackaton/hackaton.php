<?php

/**
 * Plugin Name: Hackathon Evaluator
 * Description: Avaliação de equipes via estrelas (0–5) e QR Code.
 * Version:     1.2
 * Author:      Manuela Otavio
 */
if (!defined('ABSPATH')) exit;


register_activation_hook(__FILE__, 'he_create_tables');
function he_create_tables()
{
  global $wpdb;
  $c = $wpdb->get_charset_collate();
  $sql = "
  CREATE TABLE {$wpdb->prefix}he_hackathons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL
  ) $c;
  CREATE TABLE {$wpdb->prefix}he_times (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hackathon_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    UNIQUE KEY `hackathon_time_unique` (`hackathon_id`, `nome`)
  ) $c;
  CREATE TABLE {$wpdb->prefix}he_criterios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hackathon_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    peso DECIMAL(5,2) DEFAULT 1
  ) $c;
  CREATE TABLE {$wpdb->prefix}he_avaliadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hackathon_id INT NOT NULL,
    nome VARCHAR(100),
    token CHAR(32) UNIQUE
  ) $c;
  CREATE TABLE {$wpdb->prefix}he_avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avaliador_id INT NOT NULL,
    time_id      INT NOT NULL,
    criterio_id  INT NOT NULL,
    nota TINYINT NOT NULL,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE (avaliador_id, time_id, criterio_id)
  ) $c;";
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql);
}


add_action('admin_menu', 'he_admin_menu');
function he_admin_menu()
{
  add_menu_page('Hackathon Plugin', 'Hackathon Plugin', 'manage_options', 'he_main', 'he_main_page', 'dashicons-awards', 20);
  add_submenu_page('he_main', 'Hackathons', 'Hackathons', 'manage_options', 'he_hackathons', 'he_hackathons_page');
  add_submenu_page('he_main', 'Times', 'Times', 'manage_options', 'he_times', 'he_times_page');
  add_submenu_page('he_main', 'Critérios', 'Critérios', 'manage_options', 'he_criterios', 'he_criterios_page');
  add_submenu_page('he_main', 'Avaliadores', 'Avaliadores', 'manage_options', 'he_avaliadores', 'he_avaliadores_page');
  add_submenu_page('he_main', 'Resultados', 'Resultados', 'manage_options', 'he_resultados', 'he_resultados_page');
}


function he_main_page()
{
  echo '<div class="wrap"><h1>Hackathon Evaluator</h1><p>Use os submenus para gerenciar eventos, times, critérios, avaliadores e resultados.</p></div>';
}
function he_hackathons_page()
{
  global $wpdb;


  if (isset($_POST['he_save_hackathon']) && check_admin_referer('he_save_hackathon')) {
    $wpdb->insert(
      "{$wpdb->prefix}he_hackathons",
      [
        'nome'        => sanitize_text_field($_POST['nome']),
        'data_inicio' => sanitize_text_field($_POST['data_inicio']),
        'data_fim'    => sanitize_text_field($_POST['data_fim'])
      ],
      ['%s', '%s', '%s']
    );
    echo '<div class="notice notice-success"><p>Hackathon salvo!</p></div>';
  }


  echo '<div class="wrap"><h1>Hackathons</h1>
    <form method="post">';

  wp_nonce_field('he_save_hackathon');
  echo '
      <table class="form-table">
        <tr>
          <th><label for="nome">Nome</label></th>
          <td><input name="nome" id="nome" type="text" required class="regular-text"></td>
        </tr>
        <tr>
          <th><label for="data_inicio">Data Início</label></th>
          <td><input name="data_inicio" id="data_inicio" type="date" required></td>
        </tr>
        <tr>
          <th><label for="data_fim">Data Fim</label></th>
          <td><input name="data_fim" id="data_fim" type="date" required></td>
        </tr>
      </table>
      <p><button type="submit" name="he_save_hackathon" class="button button-primary">Salvar</button></p>
    </form>
  </div>';


  $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}he_hackathons ORDER BY id DESC");
  if ($rows) {
    echo '<table class="wp-list-table widefat fixed striped">
      <thead>
        <tr><th>ID</th><th>Nome</th><th>Início</th><th>Fim</th></tr>
      </thead>
      <tbody>';
    foreach ($rows as $h) {
      echo "<tr>
              <td>{$h->id}</td>
              <td>{$h->nome}</td>
              <td>{$h->data_inicio}</td>
              <td>{$h->data_fim}</td>
            </tr>";
    }
    echo '</tbody></table>';
  }
}
function he_times_page()
{
  global $wpdb;
  echo '<div class="wrap"><h1>Times</h1>';

  if (isset($_POST['he_save_time']) && check_admin_referer('he_save_time')) {
    $hackathon_id = intval($_POST['hackathon_id']);
    $nome         = sanitize_text_field($_POST['nome']);

    $exists = $wpdb->get_var($wpdb->prepare(
      "
        SELECT COUNT(*) 
          FROM {$wpdb->prefix}he_times
         WHERE hackathon_id = %d
           AND nome         = %s
      ",
      $hackathon_id,
      $nome
    ));

    if ($exists) {
      echo '<div class="notice notice-error"><p>Já existe um time com esse nome neste hackathon.</p></div>';
    } else {
     
      $res = $wpdb->insert(
        "{$wpdb->prefix}he_times",
        [
          'hackathon_id' => $hackathon_id,
          'nome'         => $nome
        ],
        ['%d', '%s']
      );
      if (false === $res) {
       
        echo '<div class="notice notice-error"><p>Erro ao salvar time: '
          . esc_html($wpdb->last_error)
          . '</p></div>';
      } else {
        echo '<div class="notice notice-success"><p>Time salvo com sucesso!</p></div>';
      }
    }
  }


  echo '
    <form method="post">
      ' . wp_nonce_field('he_save_time', '_wpnonce', true, false) . '
      <table class="form-table">
        <tr>
          <th><label for="nome">Nome do Time</label></th>
          <td><input name="nome" id="nome" type="text" required class="regular-text"></td>
        </tr>
      </table>
      <p><button type="submit" name="he_save_time" class="button button-primary">Salvar Time</button></p>
    </form>';

  $rows = $wpdb->get_results("
    SELECT t.id, t.nome AS time, h.nome AS hackathon
      FROM {$wpdb->prefix}he_times t
      JOIN {$wpdb->prefix}he_hackathons h ON h.id = t.hackathon_id
     ORDER BY t.id DESC
  ");
  if ($rows) {
    echo '<table class="wp-list-table widefat fixed striped">
      <thead><tr><th>ID</th><th>Time</th><th>Hackathon</th></tr></thead><tbody>';
    foreach ($rows as $r) {
      echo "<tr>
              <td>{$r->id}</td>
              <td>" . esc_html($r->time) . "</td>
              <td>" . esc_html($r->hackathon) . "</td>
            </tr>";
    }
    echo '</tbody></table>';
  }

  echo '</div>';
}

function he_criterios_page()
{
  global $wpdb;

  if (isset($_POST['he_save_criterio']) && check_admin_referer('he_save_criterio')) {
    $wpdb->insert(
      "{$wpdb->prefix}he_criterios",
      [
        'hackathon_id' => intval($_POST['hackathon_id']),
        'nome'         => sanitize_text_field($_POST['nome']),
        'peso'         => floatval($_POST['peso'])
      ],
      ['%d', '%s', '%f']
    );
    echo '<div class="notice notice-success"><p>Critério salvo!</p></div>';
  }

  $hackathons = $wpdb->get_results("SELECT id,nome FROM {$wpdb->prefix}he_hackathons ORDER BY nome ASC");

  echo '<div class="wrap"><h1>Critérios</h1>
    <form method="post">';
  wp_nonce_field('he_save_criterio');
  echo '
      <table class="form-table">
        <tr>
          <th><label for="hackathon_id">Hackathon</label></th>
          <td>
            <select name="hackathon_id" id="hackathon_id" required>
              <option value="">Selecione...</option>';
  foreach ($hackathons as $h) {
    echo "<option value=\"{$h->id}\">{$h->nome}</option>";
  }
  echo      '</select>
          </td>
        </tr>
        <tr>
          <th><label for="nome">Nome do Critério</label></th>
          <td><input name="nome" id="nome" type="text" required class="regular-text"></td>
        </tr>
        <tr>
          <th><label for="peso">Peso</label></th>
          <td><input name="peso" id="peso" type="number" step="0.01" value="1.00" required></td>
        </tr>
      </table>
      <p><button type="submit" name="he_save_criterio" class="button button-primary">Salvar Critério</button></p>
    </form>
  </div>';

 
  $rows = $wpdb->get_results("
    SELECT c.id, c.nome AS criterio, c.peso, h.nome AS hackathon
      FROM {$wpdb->prefix}he_criterios c
      JOIN {$wpdb->prefix}he_hackathons h ON h.id = c.hackathon_id
     ORDER BY c.id DESC
  ");
  if ($rows) {
    echo '<table class="wp-list-table widefat fixed striped">
      <thead>
        <tr><th>ID</th><th>Critério</th><th>Peso</th><th>Hackathon</th></tr>
      </thead>
      <tbody>';
    foreach ($rows as $r) {
      echo "<tr>
              <td>{$r->id}</td>
              <td>{$r->criterio}</td>
              <td>{$r->peso}</td>
              <td>{$r->hackathon}</td>
            </tr>";
    }
    echo '</tbody></table>';
  }
}
function he_avaliadores_page()
{
  global $wpdb;

 
  if (isset($_POST['he_save_avaliador']) && check_admin_referer('he_save_avaliador')) {
  
    $token = bin2hex(random_bytes(16));
    $wpdb->insert(
      "{$wpdb->prefix}he_avaliadores",
      [
        'hackathon_id' => intval($_POST['hackathon_id']),
        'nome'         => sanitize_text_field($_POST['nome']),
        'token'        => $token
      ],
      ['%d', '%s', '%s']
    );
    echo '<div class="notice notice-success"><p>Avaliador salvo! Token gerado.</p></div>';
  }

 
  $hackathons = $wpdb->get_results("SELECT id,nome FROM {$wpdb->prefix}he_hackathons ORDER BY nome ASC");

  echo '<div class="wrap"><h1>Avaliadores</h1>
    <form method="post">';
  wp_nonce_field('he_save_avaliador');
  echo '
      <table class="form-table">
        <tr>
          <th><label for="hackathon_id">Hackathon</label></th>
          <td>
            <select name="hackathon_id" id="hackathon_id" required>
              <option value="">Selecione...</option>';
  foreach ($hackathons as $h) {
    echo "<option value=\"{$h->id}\">{$h->nome}</option>";
  }
  echo      '</select>
          </td>
        </tr>
        <tr>
          <th><label for="nome">Nome do Avaliador</label></th>
          <td><input name="nome" id="nome" type="text" required class="regular-text"></td>
        </tr>
      </table>
      <p><button type="submit" name="he_save_avaliador" class="button button-primary">Salvar Avaliador</button></p>
    </form>
  </div>';

 
  $rows = $wpdb->get_results("
    SELECT a.id, a.nome, a.token, h.nome AS hackathon
      FROM {$wpdb->prefix}he_avaliadores a
      JOIN {$wpdb->prefix}he_hackathons h ON h.id = a.hackathon_id
     ORDER BY a.id DESC
  ");

  if ($rows) {
    echo '<table class="wp-list-table widefat fixed striped">
      <thead>
        <tr><th>ID</th><th>Avaliador</th><th>Hackathon</th><th>Token</th><th>Link</th><th>QR Code</th></tr>
      </thead>
      <tbody>';
    foreach ($rows as $r) {

      $url = esc_url(site_url("/avaliar/?token={$r->token}"));
   
      $qr = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($url) . "&size=80x80";
      echo "<tr>
              <td>{$r->id}</td>
              <td>{$r->nome}</td>
              <td>{$r->hackathon}</td>
              <td style='font-family:monospace'>{$r->token}</td>
              <td><a href=\"{$url}\" target=\"_blank\">Abrir</a></td>
              <td><img src=\"{$qr}\" alt=\"QR Code\" /></td>
            </tr>";
    }
    echo '</tbody></table>';
  }
}

function he_avaliadores_form_shortcode()
{
  global $wpdb;
  $output = '';

  if (isset($_POST['he_avaliadores_form_submit']) && check_admin_referer('he_avaliadores_form_action', 'he_avaliadores_form_nonce')) {
    $hackathon_id = intval($_POST['hackathon_id']);
    $nome         = sanitize_text_field($_POST['nome']);

    if (empty($hackathon_id) || empty($nome)) {
      $output .= '<div class="he-notice he-notice-error"><p>Selecione um hackathon e informe o nome do avaliador.</p></div>';
    } else {
   
      $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}he_avaliadores WHERE hackathon_id = %d AND nome = %s",
        $hackathon_id,
        $nome
      ));
      if ($exists) {
        $output .= '<div class="he-notice he-notice-error"><p>Este avaliador já está cadastrado neste hackathon.</p></div>';
      } else {
  
        $token = bin2hex(random_bytes(16));
        $res = $wpdb->insert(
          "{$wpdb->prefix}he_avaliadores",
          [
            'hackathon_id' => $hackathon_id,
            'nome'         => $nome,
            'token'        => $token
          ],
          ['%d', '%s', '%s']
        );
        if (false === $res) {
          $output .= '<div class="he-notice he-notice-error"><p>Erro ao cadastrar avaliador: '
            . esc_html($wpdb->last_error)
            . '</p></div>';
        } else {
          $url = esc_url(site_url("/avaliar/?token={$token}"));
          $output .= '<div class="he-notice he-notice-success"><p>Avaliador cadastrado! Link de acesso: '
            . '<a href="' . $url . '" target="_blank">' . $url . '</a>'
            . '</p></div>';
        }
      }
    }
  }


  $hackathons = $wpdb->get_results(
    "SELECT id,nome FROM {$wpdb->prefix}he_hackathons ORDER BY nome ASC"
  );


  $output .= '<form method="post" class="he-avaliadores-form">';
  $output .= wp_nonce_field('he_avaliadores_form_action', 'he_avaliadores_form_nonce', true, false);
  $output .= '<p><label for="he_hackathon_id">Hackathon</label><br>
                <select name="hackathon_id" id="he_hackathon_id" required>
                  <option value="">– selecione –</option>';
  foreach ($hackathons as $h) {
    $output .= "<option value=\"{$h->id}\">" . esc_html($h->nome) . "</option>";
  }
  $output .= '</select></p>';
  $output .= '<p><label for="he_nome_avaliador">Nome do Avaliador</label><br>
                <input name="nome" id="he_nome_avaliador" type="text" required></p>';
  $output .= '<p><button type="submit" name="he_avaliadores_form_submit" class="button">Cadastrar Avaliador</button></p>';
  $output .= '</form>';

  return $output;
}
add_shortcode('he_avaliadores_form', 'he_avaliadores_form_shortcode');

function he_resultados_page(){
    global $wpdb;

    echo '<div class="wrap"><h1>Resultados</h1>';

    echo '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '" style="margin-bottom:20px;">';
    
      echo '<input type="hidden" name="action" value="he_export_results">';
      wp_nonce_field('he_export_results');
      echo '<button type="submit" class="button">Exportar CSV</button>';
    echo '</form>';

 
    $rows = $wpdb->get_results("
      SELECT t.nome AS time,
             ROUND(SUM(a.nota * c.peso)/SUM(c.peso),2) AS score
        FROM {$wpdb->prefix}he_avaliacoes a
        JOIN {$wpdb->prefix}he_times t     ON t.id = a.time_id
        JOIN {$wpdb->prefix}he_criterios c ON c.id = a.criterio_id
       GROUP BY t.id
       ORDER BY score DESC
    ");

    if ( $rows ) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Time</th><th>Score</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
            echo '<tr><td>'.esc_html($r->time).'</td><td>'.esc_html($r->score).'</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>Sem avaliações registradas.</p>';
    }

    echo '</div>';
}
add_shortcode('he_export_csv', 'he_resultados_page');

add_action('admin_post_he_export_results',   'he_export_results_handler');
add_action('admin_post_nopriv_he_export_results','he_export_results_handler');
function he_export_results_handler(){
    if ( empty($_REQUEST['_wpnonce']) || ! wp_verify_nonce($_REQUEST['_wpnonce'],'he_export_results') ) {
        wp_die('Não autorizado');
    }
    global $wpdb;
    $rows = $wpdb->get_results("
      SELECT t.nome AS Time,
             ROUND(SUM(a.nota * c.peso)/SUM(c.peso),2) AS Score
        FROM {$wpdb->prefix}he_avaliacoes a
        JOIN {$wpdb->prefix}he_times t     ON t.id = a.time_id
        JOIN {$wpdb->prefix}he_criterios c ON c.id = a.criterio_id
       GROUP BY t.id
       ORDER BY Score DESC
    ", ARRAY_A);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="hackathon_resultados.csv"');
    $out = fopen('php://output','w');
    if ( ! empty($rows) ) {
        fputcsv( $out, array_keys($rows[0]) );
        foreach ( $rows as $row ) {
            fputcsv( $out, $row );
        }
    }
    fclose($out);
    exit;
}


function he_times_form_shortcode()
{
  global $wpdb;
  $output = '';


  if (isset($_POST['he_times_form_submit']) && check_admin_referer('he_times_form_action', 'he_times_form_nonce')) {
    $hackathon_id = intval($_POST['hackathon_id']);
    $nome         = sanitize_text_field($_POST['nome']);

    if (empty($hackathon_id) || empty($nome)) {
      $output .= '<div class="he-notice he-notice-error"><p>Por favor, selecione um hackathon e digite o nome do time.</p></div>';
    } else {
     
      $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}he_times WHERE hackathon_id = %d AND nome = %s",
        $hackathon_id,
        $nome
      ));

      if ($exists) {
        $output .= '<div class="he-notice he-notice-error"><p>Já existe um time com esse nome neste hackathon.</p></div>';
      } else {
     
        $result = $wpdb->insert(
          "{$wpdb->prefix}he_times",
          ['hackathon_id' => $hackathon_id, 'nome' => $nome],
          ['%d', '%s']
        );

        if (false === $result) {
          $output .= '<div class="he-notice he-notice-error"><p>Ocorreu um erro ao cadastrar o time. Tente novamente.</p></div>';
        } else {
          $output .= '<div class="he-notice he-notice-success"><p>Time cadastrado com sucesso!</p></div>';
        }
      }
    }
  }


  $hackathons = $wpdb->get_results("SELECT id,nome FROM {$wpdb->prefix}he_hackathons ORDER BY nome ASC");


  $output .= '<form method="post" class="he-times-form">';
  $output .= wp_nonce_field('he_times_form_action', 'he_times_form_nonce', true, false);
  $output .= '<p><label for="he_hackathon_id">Hackathon</label><br>
              <select name="hackathon_id" id="he_hackathon_id" required>
                <option value="">– selecione –</option>';
  foreach ($hackathons as $h) {
    $output .= "<option value=\"{$h->id}\">" . esc_html($h->nome) . "</option>";
  }
  $output .= '</select></p>';
  $output .= '<p><label for="he_nome_time">Nome do Time</label><br>
              <input name="nome" id="he_nome_time" type="text" required></p>';
  $output .= '<p><button type="submit" name="he_times_form_submit">Cadastrar Time</button></p>';
  $output .= '</form>';

  return $output;
}
add_shortcode('he_times_form', 'he_times_form_shortcode');


add_shortcode('he_evaluate', 'he_render_evaluation');
function he_render_evaluation()
{
  if (!isset($_GET['token'])) return '<p><strong>Token ausente.</strong></p>';
  global $wpdb;
  $token = sanitize_text_field($_GET['token']);
  $aval = $wpdb->get_row($wpdb->prepare(
    "SELECT id,hackathon_id FROM {$wpdb->prefix}he_avaliadores WHERE token=%s",
    $token
  ));
  if (!$aval) return '<p><strong>Token inválido.</strong></p>';

  $times = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}he_times WHERE hackathon_id=%d",
    $aval->hackathon_id
  ));
  $criterios = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}he_criterios WHERE hackathon_id=%d",
    $aval->hackathon_id
  ));

  ob_start();
  echo '<div class="he-eval-container">';
  foreach ($times as $t) {
    echo "<div class='he-team' data-id='{$t->id}'><h3>{$t->nome}</h3>";
    foreach ($criterios as $c) {
      $prev = $wpdb->get_var($wpdb->prepare(
        "SELECT nota FROM {$wpdb->prefix}he_avaliacoes WHERE avaliador_id=%d AND time_id=%d AND criterio_id=%d",
        $aval->id,
        $t->id,
        $c->id
      ));
      $stars = intval($prev);
      echo "<div class='he-crit'><strong>{$c->nome}:</strong> <span class='he-stars' data-crit='{$c->id}' data-note='{$stars}'>" .
        str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) .
        "</span></div>";
    }
    echo "</div>";
  }
  echo '</div>';
?>
  <script>
    document.querySelectorAll('.he-stars').forEach(el => {
      el.addEventListener('click', e => {
       
        e.preventDefault();
        let currentStars = parseInt(el.dataset.note, 10);
   
        let newNote = (currentStars < 5) ? currentStars + 1 : 0;
        el.dataset.note = newNote;

        const teamId = el.closest('.he-team').dataset.id;
        const critId = el.dataset.crit;
        const token = '<?php echo esc_js($token); ?>';
        const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

        const url = `${ajaxUrl}?action=he_save&token=${token}&time=${teamId}&crit=${critId}&nota=${newNote}`;

        el.textContent = '★'.repeat(newNote) + '☆'.repeat(5 - newNote);

        fetch(url)
          .then(response => response.json())
          .then(data => {
            if (!data.success) {
            
              console.error('Falha ao salvar a avaliação.');
              el.dataset.note = currentStars; 
              el.textContent = '★'.repeat(currentStars) + '☆'.repeat(5 - currentStars); 
            }
          })
          .catch(error => {
            console.error('Erro na requisição:', error);
          
            el.dataset.note = currentStars;
            el.textContent = '★'.repeat(currentStars) + '☆'.repeat(5 - currentStars);
          });
      });
    });
  </script>
<?php
  return ob_get_clean();
}

add_action('wp_ajax_he_save', 'he_save');
add_action('wp_ajax_nopriv_he_save', 'he_save');
function he_save()
{
  global $wpdb;

  if (!isset($_GET['token'], $_GET['time'], $_GET['crit'], $_GET['nota'])) {
    wp_send_json_error('Parâmetros ausentes.');
  }

  $token = sanitize_text_field($_GET['token']);
  $time  = intval($_GET['time']);
  $crit  = intval($_GET['crit']);
  $nota  = intval($_GET['nota']);

  if ($nota < 0 || $nota > 5) {
    wp_send_json_error('Nota inválida.');
  }

  $avaliador_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}he_avaliadores WHERE token=%s",
    $token
  ));

  if (!$avaliador_id) {
    wp_send_json_error('Token inválido.');
  }

  $result = $wpdb->replace(
    "{$wpdb->prefix}he_avaliacoes",
    ['avaliador_id' => $avaliador_id, 'time_id' => $time, 'criterio_id' => $crit, 'nota' => $nota],
    ['%d', '%d', '%d', '%d']
  );

  if (false === $result) {
    wp_send_json_error('Erro no banco de dados.');
  }

  wp_send_json_success();
}
