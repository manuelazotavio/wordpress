<?php

/**
 * Plugin Name: Hackathon Evaluator
 * Description: Avaliação de equipes via estrelas (0–5) e QR Code.
 * Version:     2.2
 * Author:      Manuela Otavio & Gemini
 */
if (!defined('ABSPATH')) exit;
function he_create_roles() {
    // Capacidades do Avaliador
    add_role('avaliador', 'Avaliador do Hackathon', ['read' => true, 'avaliar_times' => true]);
    // Capacidades do Admin do Hackathon (baseado em Editor)
    $editor_role = get_role('editor');
    $admin_caps = $editor_role ? $editor_role->capabilities : [];
    $admin_caps['gerenciar_hackathon'] = true;
    add_role('admin_hackathon', 'Admin do Hackathon', $admin_caps);
    // Adiciona as novas capacidades ao Admin geral
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('gerenciar_hackathon');
        $admin_role->add_cap('avaliar_times');
    }
}

function he_remove_roles() {
    remove_role('admin_hackathon');
    remove_role('avaliador');
}
function shortcode_conteudo_restrito($atts, $content = null) {
    $a = shortcode_atts(['capability' => 'read', 'mensagem' => 'Você não tem permissão para ver este conteúdo.'], $atts);
    if (current_user_can($a['capability']) && !is_null($content)) {
        return do_shortcode($content);
    }
    $login_link = '<a href="' . wp_login_url(get_permalink()) . '">faça o login</a>';
    return '<div class="conteudo-bloqueado">' . esc_html($a['mensagem']) . ' Por favor, ' . $login_link . '.</div>';
}
add_shortcode('conteudo_restrito', 'shortcode_conteudo_restrito');

register_activation_hook(__FILE__, 'he_create_tables');
function he_create_tables()
{
  global $wpdb;
  $c = $wpdb->get_charset_collate();
  // A coluna 'nota' é DECIMAL para suportar meias estrelas (ex: 3.5)
  // A coluna 'peso' nos critérios representa a pontuação máxima.
  $sql = "
    CREATE TABLE {$wpdb->prefix}he_times (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nome VARCHAR(100) NOT NULL UNIQUE
    ) $c;
    CREATE TABLE {$wpdb->prefix}he_criterios (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nome VARCHAR(100) NOT NULL,
      peso DECIMAL(5,2) DEFAULT 1.00
    ) $c;
    CREATE TABLE {$wpdb->prefix}he_avaliadores (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nome VARCHAR(100),
      token CHAR(32) UNIQUE
    ) $c;
    CREATE TABLE {$wpdb->prefix}he_avaliacoes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      avaliador_id INT NOT NULL,
      time_id      INT NOT NULL,
      criterio_id  INT NOT NULL,
      nota DECIMAL(3,1) NOT NULL,
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
  add_submenu_page('he_main', 'Times', 'Times', 'manage_options', 'he_times', 'he_times_page');
  add_submenu_page('he_main', 'Critérios', 'Critérios', 'manage_options', 'he_criterios', 'he_criterios_page');
  add_submenu_page('he_main', 'Avaliadores', 'Avaliadores', 'manage_options', 'he_avaliadores', 'he_avaliadores_page');
  add_submenu_page('he_main', 'Resultados', 'Resultados', 'manage_options', 'he_resultados', 'he_resultados_page');
}

function shortcode_conteudo_logado($atts, $content = null) {
    // Verifica se o usuário está logado
    if (is_user_logged_in() && !is_null($content) && !is_feed()) {
        // Se estiver logado, mostra o conteúdo que está dentro do shortcode
        return do_shortcode($content);
    }
    return 'Este conteúdo está disponível apenas para administradores. Por favor, <a href="' . home_url('/login/') . '">faça o login</a> para acessar.';
}

add_shortcode('membros_apenas', 'shortcode_conteudo_logado');
function processa_formulario_cadastro() {
    // Verifica se o formulário de cadastro foi enviado
    if (isset($_POST['submit_registro']) && isset($_POST['registro_nonce_field'])) {
        // Verifica o nonce de segurança
        if (!wp_verify_nonce($_POST['registro_nonce_field'], 'registro_nonce')) {
            wp_die('Erro de segurança!');
        }

        // Pega e sanitiza os dados
        $user_login = sanitize_user($_POST['user_login']);
        $user_email = sanitize_email($_POST['user_email']);
        $user_pass  = $_POST['user_pass'];
        $user_pass_confirm = $_POST['user_pass_confirm'];

        // Cria um array para armazenar os erros e redirecionar
        $errors = [];

        // Validações
        if (empty($user_login) || empty($user_email) || empty($user_pass)) {
            $errors['empty'] = 'true';
        }
        if ($user_pass !== $user_pass_confirm) {
            $errors['password_mismatch'] = 'true';
        }
        if (username_exists($user_login)) {
            $errors['username_exists'] = 'true';
        }
        if (email_exists($user_email)) {
            $errors['email_exists'] = 'true';
        }
        if (!empty($errors)) {
          
            $redirect_url = wp_get_referer();
           
            $redirect_url = add_query_arg($errors, $redirect_url);
         
            wp_redirect($redirect_url);
            exit;
        }

    
        $user_id = wp_create_user($user_login, $user_pass, $user_email);

        if (is_wp_error($user_id)) {
         
            wp_redirect(add_query_arg(['wp_error' => 'true'], wp_get_referer()));
            exit;
        } else {
       
            $login_page_url = home_url('/login/'); 
            wp_redirect(add_query_arg(['registration' => 'success'], $login_page_url));
            exit;
        }
    }
}
add_action('init', 'processa_formulario_cadastro');


//======================================================================
// 2. SHORTCODE PARA EXIBIR O FORMULÁRIO DE CADASTRO -> [meu_cadastro]
//======================================================================

function exibe_shortcode_cadastro($atts) {
    ob_start(); // Inicia um buffer de saída para capturar o HTML

    // Verifica se o usuário já está logado
    if (is_user_logged_in()) {
        echo 'Você já está logado. Não é necessário se cadastrar.';
        return ob_get_clean();
    }

    // Exibe mensagens de erro com base nos parâmetros da URL
    if (isset($_GET['empty'])) { echo '<p style="color:red;">Todos os campos são obrigatórios.</p>'; }
    if (isset($_GET['password_mismatch'])) { echo '<p style="color:red;">As senhas não coincidem.</p>'; }
    if (isset($_GET['username_exists'])) { echo '<p style="color:red;">Este nome de usuário já existe.</p>'; }
    if (isset($_GET['email_exists'])) { echo '<p style="color:red;">Este e-mail já está em uso.</p>'; }
    if (isset($_GET['wp_error'])) { echo '<p style="color:red;">Ocorreu um erro ao criar sua conta. Tente novamente.</p>'; }
    
    ?>
    <form id="registration-form" action="" method="post">
        <p>
            <label for="user_login">Nome de Usuário</label>
            <input type="text" name="user_login" id="user_login" required>
        </p>
        <p>
            <label for="user_email">E-mail</label>
            <input type="email" name="user_email" id="user_email" required>
        </p>
        <p>
            <label for="user_pass">Senha</label>
            <input type="password" name="user_pass" id="user_pass" required>
        </p>
        <p>
            <label for="user_pass_confirm">Confirmar Senha</label>
            <input type="password" name="user_pass_confirm" id="user_pass_confirm" required>
        </p>
        <?php wp_nonce_field('registro_nonce', 'registro_nonce_field'); ?>
        <p>
            <input type="submit" name="submit_registro" value="Registrar">
        </p>
    </form>
    <?php
    return ob_get_clean(); // Retorna o HTML capturado
}
add_shortcode('meu_cadastro', 'exibe_shortcode_cadastro');


//======================================================================
// 3. LÓGICA DE PROCESSAMENTO DO LOGIN
//======================================================================

function processa_formulario_login() {
    if (isset($_POST['submit_login']) && isset($_POST['login_nonce_field'])) {
        if (!wp_verify_nonce($_POST['login_nonce_field'], 'login_nonce')) {
            wp_die('Erro de segurança!');
        }

        $creds = [
            'user_login'    => sanitize_user($_POST['log']),
            'user_password' => $_POST['pwd'],
            'remember'      => isset($_POST['rememberme']),
        ];

        $user = wp_signon($creds, false);

        if (is_wp_error($user)) {
            // Erro no login, redireciona de volta com um parâmetro de erro
            $redirect_url = wp_get_referer();
            wp_redirect(add_query_arg(['login' => 'failed'], $redirect_url));
            exit;
        } else {
            // Sucesso! Redireciona para a página de perfil
            // Assumindo que sua página de perfil está em /perfil/
            wp_redirect(home_url('/admin-2/'));
            exit;
        }
    }
}
add_action('init', 'processa_formulario_login');


//======================================================================
// 4. SHORTCODE PARA EXIBIR O FORMULÁRIO DE LOGIN -> [meu_login]
//======================================================================

function exibe_shortcode_login($atts) {
    ob_start();

    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        echo 'Olá, ' . esc_html($user->display_name) . '! Você já está logado. <a href="' . wp_logout_url(home_url()) . '">Sair</a>';
        return ob_get_clean();
    }
    
    // Mensagem de erro de login
    if (isset($_GET['login']) && $_GET['login'] == 'failed') {
        echo '<p style="color:red;"><strong>ERRO</strong>: Usuário ou senha inválidos.</p>';
    }

    // Mensagem de sucesso de cadastro
    if (isset($_GET['registration']) && $_GET['registration'] == 'success') {
        echo '<p style="color:green;">Cadastro realizado com sucesso! Por favor, faça o login.</p>';
    }
    
    ?>
    <form name="loginform" id="loginform" action="" method="post">
        <p>
            <label for="user_login">Nome de Usuário ou E-mail</label>
            <input type="text" name="log" id="user_login" class="input" value="" size="20" required/>
        </p>
        <p>
            <label for="user_pass">Senha</label>
            <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" required/>
        </p>
        <p class="login-remember">
            <label><input name="rememberme" type="checkbox" id="rememberme" value="forever"> Lembrar-me</label>
        </p>
        <?php wp_nonce_field('login_nonce', 'login_nonce_field'); ?>
        <p class="login-submit">
            <input type="submit" name="submit_login" id="wp-submit" class="button button-primary" value="Entrar" />
        </p>
    </form>
     <p>Não tem uma conta? Use o shortcode [meu_cadastro] em uma página de cadastro.</p>
    <?php
    return ob_get_clean();
}
add_shortcode('meu_login', 'exibe_shortcode_login');


function he_main_page()
{
  echo '<div class="wrap"><h1>Hackathon Evaluator</h1><p>Use os submenus para gerenciar times, critérios, avaliadores e resultados.</p>';
  echo '<h3>Shortcodes disponíveis:</h3>';
  echo '<ul>';
  echo '<li><code>[he_manage_times]</code> - Para gerenciar times no front-end.</li>';
  echo '<li><code>[he_manage_criterios]</code> - Para gerenciar critérios no front-end.</li>';
  echo '<li><code>[he_manage_avaliadores]</code> - Para gerenciar avaliadores no front-end.</li>';
  echo '<li><code>[he_evaluate]</code> - Deve ser usado na página de avaliação (ex: /avaliar). Ele lê o token da URL e exibe a interface de votação.</li>';
  echo '</ul></div>';
}



function he_get_management_styles()
{
  return "
    <style>
        .he-manage-container { font-family: sans-serif; max-width: 700px; margin: auto; }
        .he-form-section, .he-list-section { border: 1px solid #ccc; padding: 20px;  padding-bottom: 5px; border-radius: 8px; margin-bottom: 20px; }
        .he-list { list-style-type: none; padding: 0; }
        .he-list li { display: flex; align-items: flex-start; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; flex-wrap: wrap;}
        .he-list .item-details { flex-grow: 1; }
        .he-list .item-name { font-weight: bold; }
        .he-list .actions { margin-left: 10px; flex-shrink: 0; }
        .he-list .actions button, .he-list .actions .button { margin-left: 5px; cursor: pointer; }
        .he-edit-form { display: none; margin-top: 10px; }
        .he-notice { padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; }
        .he-notice-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .he-notice-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .he-link-details { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #eee; display: flex; align-items: center; gap: 15px;}
        .he-link-details p { margin: 0; word-break: break-all; }
    </style>";
}

function he_get_management_script()
{
  return "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.edit-btn, .cancel-edit-btn, .button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.getAttribute('data-id');
                const type = e.target.getAttribute('data-type');
                const detailsSpan = document.getElementById(type + '-details-' + id);
                const editForm = document.getElementById('edit-form-' + type + '-' + id);
                const editButton = e.target.closest('li').querySelector('.edit-btn');
                const isEditing = editForm.style.display === 'block';
                detailsSpan.style.display = isEditing ? 'block' : 'none';
                editForm.style.display = isEditing ? 'none' : 'block';
                if(editButton) editButton.style.display = isEditing ? 'inline-block' : 'none';
            });
        });
    });

    </script>";
}


add_shortcode('he_manage_times', 'he_manage_times_shortcode');
function he_manage_times_shortcode()
{

     if (!current_user_can('gerenciar_hackathon')) {
        return 'Você não tem permissão para gerenciar times.';
    }
  global $wpdb;
  $output = '';

  if (isset($_POST['he_manage_nonce']) && wp_verify_nonce($_POST['he_manage_nonce'], 'he_manage_action')) {
    $action = sanitize_text_field($_POST['action']);

    if ($action === 'add_time') {
      $nome = sanitize_text_field($_POST['nome']);
      if (empty($nome)) {
        $output .= '<div class="he-notice he-notice-error"><p>Por favor, digite o nome do time.</p></div>';
      } else {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}he_times WHERE nome = %s", $nome));
        if ($exists) {
          $output .= '<div class="he-notice he-notice-error"><p>Já existe um time com esse nome.</p></div>';
        } else {
          $wpdb->insert("{$wpdb->prefix}he_times", ['nome' => $nome], ['%s']);
          $output .= '<div class="he-notice he-notice-success"><p>Time cadastrado com sucesso!</p></div>';
        }
      }
    }

    if ($action === 'update_time') {
      $time_id = intval($_POST['time_id']);
      $nome = sanitize_text_field($_POST['nome']);
      if (!empty($nome) && $time_id > 0) {
        $wpdb->update("{$wpdb->prefix}he_times", ['nome' => $nome], ['id' => $time_id], ['%s'], ['%d']);
        $output .= '<div class="he-notice he-notice-success"><p>Time atualizado com sucesso!</p></div>';
      } else {
        $output .= '<div class="he-notice he-notice-error"><p>Nome do time não pode ser vazio.</p></div>';
      }
    }

    if ($action === 'delete_time') {
      $time_id = intval($_POST['time_id']);
      if ($time_id > 0) {
        $wpdb->delete("{$wpdb->prefix}he_avaliacoes", ['time_id' => $time_id], ['%d']);
        $wpdb->delete("{$wpdb->prefix}he_times", ['id' => $time_id], ['%d']);
        $output .= '<div class="he-notice he-notice-success"><p>Time e suas avaliações foram excluídos com sucesso.</p></div>';
      }
    }
  }
  ob_start();
  echo he_get_management_styles();
?>
  <div class="he-manage-container">
    <?php echo $output; ?>
    <div class="he-form-section">
      <h3>Adicionar Novo Time</h3>
      <form method="post">
        <input type="hidden" name="action" value="add_time">
        <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
        <p>
          <label for="he_nome_time">Nome do Time</label><br>
          <input name="nome" id="he_nome_time" type="text" required style="width: 100%;">
        </p>
        <p><button type="submit">Cadastrar Time</button></p>
      </form>
    </div>
    <div class="he-list-section">
      <h3>Times Cadastrados</h3>
      <?php
      $teams = $wpdb->get_results("SELECT id, nome FROM {$wpdb->prefix}he_times ORDER BY nome");
      if ($teams) {
        echo '<ul class="he-list">';
        foreach ($teams as $t) {
      ?>
          <li>
            <div class="item-details">
              <span id="time-details-<?php echo $t->id; ?>"><span class="item-name"><?php echo esc_html($t->nome); ?></span></span>
              <div class="he-edit-form" id="edit-form-time-<?php echo $t->id; ?>">
                <form method="post" style="display:inline-flex; align-items:center; gap: 5px;">
                  <input type="hidden" name="action" value="update_time">
                  <input type="hidden" name="time_id" value="<?php echo $t->id; ?>">
                  <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
                  <input type="text" name="nome" value="<?php echo esc_attr($t->nome); ?>" required>
                  <button type="submit">Salvar</button>
                  <button type="button" class="cancel-edit-btn" data-id="<?php echo $t->id; ?>" data-type="time">Cancelar</button>
                </form>
              </div>
            </div>
            <div class="actions">
              <button type="button" class="edit-btn" data-id="<?php echo $t->id; ?>" data-type="time">Editar</button>
              <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este time?');">
                <input type="hidden" name="action" value="delete_time">
                <input type="hidden" name="time_id" value="<?php echo $t->id; ?>">
                <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
                <button type="submit" class="button-link-delete">Excluir</button>
              </form>
            </div>
          </li>
      <?php
        }
        echo '</ul>';
      } else {
        echo '<p>Nenhum time cadastrado ainda.</p>';
      }
      ?>
    </div>
  </div>
<?php
  echo he_get_management_script();
  return ob_get_clean();
}


add_shortcode('he_manage_criterios', 'he_manage_criterios_shortcode');
function he_manage_criterios_shortcode()
{
  global $wpdb;
  $output = '';

  if (!current_user_can('gerenciar_hackathon')) {
        return 'Você não tem permissão para gerenciar critérios.';
    }

  if (isset($_POST['he_manage_nonce']) && wp_verify_nonce($_POST['he_manage_nonce'], 'he_manage_action')) {
    $action = sanitize_text_field($_POST['action']);

    if ($action === 'add_criterio' && !empty($_POST['nome']) && isset($_POST['peso'])) {
      $nome = sanitize_text_field($_POST['nome']);
      $peso = floatval($_POST['peso']);

      $wpdb->insert("{$wpdb->prefix}he_criterios", ['nome' => $nome, 'peso' => $peso], ['%s', '%f']);
      $output .= '<div class="he-notice he-notice-success"><p>Critério adicionado com sucesso!</p></div>';
    } elseif ($action === 'update_criterio' && !empty($_POST['criterio_id'])) {
      $criterio_id = intval($_POST['criterio_id']);
      $nome = sanitize_text_field($_POST['nome']);
      $peso = floatval($_POST['peso']);
      if (!empty($nome) && $peso > 0) {
        $wpdb->update(
          "{$wpdb->prefix}he_criterios",
          ['nome' => $nome, 'peso' => $peso],
          ['id' => $criterio_id],
          ['%s', '%f'],
          ['%d']
        );
        $output .= '<div class="he-notice he-notice-success"><p>Critério atualizado com sucesso!</p></div>';
      } else {
        $output .= '<div class="he-notice he-notice-error"><p>Nome e peso do critério são obrigatórios.</p></div>';
      }
    } elseif ($action === 'delete_criterio' && !empty($_POST['criterio_id'])) {
      $criterio_id = intval($_POST['criterio_id']);
      $wpdb->delete("{$wpdb->prefix}he_criterios", ['id' => $criterio_id], ['%d']);
      $wpdb->delete("{$wpdb->prefix}he_avaliacoes", ['criterio_id' => $criterio_id], ['%d']);
      $output .= '<div class="he-notice he-notice-success"><p>Critério excluído com sucesso.</p></div>';
    }
  }

  ob_start();
  echo he_get_management_styles();
?>
  <div class="he-manage-container">
    <?php echo $output; ?>
    <div class="he-form-section">
      <h3>Adicionar Novo Critério</h3>
      <form method="post">
        <input type="hidden" name="action" value="add_criterio">
        <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
        <p>
          <label for="he_nome_criterio">Nome do Critério</label><br>
          <input name="nome" id="he_nome_criterio" type="text" required style="width: 100%;">
        </p>
        <p>
          <label for="he_peso_criterio">Pontuação Máxima (Peso)</label><br>
          <input name="peso" id="he_peso_criterio" type="number" step="0.01" required style="width: 100%;">
        </p>
        <p><button type="submit">Cadastrar Critério</button></p>
      </form>
    </div>
    <div class="he-list-section">
      <h3>Critérios Cadastrados</h3>
      <?php
      $criterios = $wpdb->get_results("SELECT id, nome, peso FROM {$wpdb->prefix}he_criterios ORDER BY id ASC");
      if ($criterios) {
        echo '<ul class="he-list">';
        foreach ($criterios as $c) {
      ?>
          <li>
            <div class="item-details">
              <span id="criterio-details-<?php echo $c->id; ?>"><span class="item-name"><?php echo esc_html($c->nome); ?></span> (Peso: <?php echo esc_html($c->peso); ?>)</span>
              <div class="he-edit-form" id="edit-form-criterio-<?php echo $c->id; ?>">
                <form method="post" style="display:inline-flex; align-items:center; gap: 5px; flex-wrap: wrap;">
                  <input type="hidden" name="action" value="update_criterio">
                  <input type="hidden" name="criterio_id" value="<?php echo $c->id; ?>">
                  <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
                  <input type="text" name="nome" value="<?php echo esc_attr($c->nome); ?>" required placeholder="Nome do critério">
                  <input type="number" name="peso" step="0.01" value="<?php echo esc_attr($c->peso); ?>" required placeholder="Peso">
                  <button type="submit">Salvar</button>
                  <button type="button" class="cancel-edit-btn" data-id="<?php echo $c->id; ?>" data-type="criterio">Cancelar</button>
                </form>
              </div>
            </div>
            <div class="actions">
              <button type="button" class="edit-btn" data-id="<?php echo $c->id; ?>" data-type="criterio">Editar</button>
              <form method="post" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este critério?')">
                <input type="hidden" name="action" value="delete_criterio">
                <input type="hidden" name="criterio_id" value="<?php echo esc_attr($c->id); ?>">
                <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
                <button type="submit" class="button-link-delete">Excluir</button>
              </form>
            </div>
          </li>
      <?php
        }
        echo '</ul>';
      } else {
        echo '<p>Nenhum critério cadastrado ainda.</p>';
      }
      ?>
    </div>
  </div>
<?php
  echo he_get_management_script();
  return ob_get_clean();
}


add_shortcode('he_manage_avaliadores', 'he_manage_avaliadores_shortcode');
function he_manage_avaliadores_shortcode()
{
  global $wpdb;
  $output = '';
if (!current_user_can('gerenciar_hackathon')) {
        return 'Você não tem permissão para gerenciar avaliadores.';
    }
  if (isset($_POST['he_manage_nonce']) && wp_verify_nonce($_POST['he_manage_nonce'], 'he_manage_action')) {
    $action = sanitize_text_field($_POST['action']);

    if ($action === 'add_avaliador') {
      $nome = sanitize_text_field($_POST['nome']);
      if (empty($nome)) {
        $output .= '<div class="he-notice he-notice-error"><p>Por favor, digite o nome do avaliador.</p></div>';
      } else {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}he_avaliadores WHERE nome = %s", $nome));
        if ($exists) {
          $output .= '<div class="he-notice he-notice-error"><p>Já existe um avaliador com esse nome.</p></div>';
        } else {
          $token = bin2hex(random_bytes(16));
          $wpdb->insert("{$wpdb->prefix}he_avaliadores", ['nome' => $nome, 'token' => $token], ['%s', '%s']);
          $output .= '<div class="he-notice he-notice-success"><p>Avaliador cadastrado com sucesso!</p></div>';
        }
      }
    }

    if ($action === 'update_avaliador') {
      $avaliador_id = intval($_POST['avaliador_id']);
      $nome = sanitize_text_field($_POST['nome']);
      if (!empty($nome) && $avaliador_id > 0) {
        $wpdb->update("{$wpdb->prefix}he_avaliadores", ['nome' => $nome], ['id' => $avaliador_id], ['%s'], ['%d']);
        $output .= '<div class="he-notice he-notice-success"><p>Avaliador atualizado com sucesso!</p></div>';
      } else {
        $output .= '<div class="he-notice he-notice-error"><p>O nome do avaliador não pode ser vazio.</p></div>';
      }
    }

    if ($action === 'delete_avaliador') {
      $avaliador_id = intval($_POST['avaliador_id']);
      if ($avaliador_id > 0) {
        $wpdb->delete("{$wpdb->prefix}he_avaliacoes", ['avaliador_id' => $avaliador_id], ['%d']);
        $wpdb->delete("{$wpdb->prefix}he_avaliadores", ['id' => $avaliador_id], ['%d']);
        $output .= '<div class="he-notice he-notice-success"><p>Avaliador e suas avaliações foram excluídos.</p></div>';
      }
    }
  }
  ob_start();
  echo he_get_management_styles();

  
?>
  <div class="he-manage-container">
    <?php echo $output; ?>
    <div class="he-form-section">
      <h3>Adicionar Novo Avaliador</h3>
      <form method="post">
        <input type="hidden" name="action" value="add_avaliador">
        <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
        <p>
          <label for="he_nome_avaliador">Nome do Avaliador</label><br>
          <input name="nome" id="he_nome_avaliador" type="text" required style="width: 100%;">
        </p>
        <p><button type="submit">Cadastrar Avaliador</button></p>
      </form>
    </div>
    <div class="he-list-section">
      <div style="display: flex; flex-direction: row; gap: 20px">
      <h3>Avaliadores Cadastrados</h3> <button type="button" id="exportPdfBtn">Exportar PDF</button>
      </div>
      <?php

      $avaliadores = $wpdb->get_results("SELECT id, nome, token FROM {$wpdb->prefix}he_avaliadores ORDER BY nome ASC");
      if ($avaliadores) {
        echo '<ul class="he-list">';
        foreach ($avaliadores as $a) {
          $url = esc_url(site_url("/avaliar/?token={$a->token}"));
          $qr_url = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($url) . "&size=80x80";
      ?>
          <li>
            <div class="item-details">
              <div id="avaliador-details-<?php echo $a->id; ?>">
                <span class="item-name"><?php echo esc_html($a->nome); ?></span>
                <div class="he-link-details">
                  <img src="<?php echo $qr_url; ?>" alt="QR Code para <?php echo esc_attr($a->nome); ?>" />

                  <p><strong>Link:</strong> <a  href="<?php echo $url; ?>" target="_blank"><?php echo $url; ?></a></p>
                 
                </div>
              </div>
              <div class="he-edit-form" id="edit-form-avaliador-<?php echo $a->id; ?>">
                <form method="post" style="display:inline-flex; align-items:center; gap: 5px;">
                  <input type="hidden" name="action" value="update_avaliador">
                  <input type="hidden" name="avaliador_id" value="<?php echo $a->id; ?>">
                  <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
                  <input type="text" name="nome" value="<?php echo esc_attr($a->nome); ?>" required>
                  <button type="submit">Salvar</button>
                  <button type="button" class="cancel-edit-btn" data-id="<?php echo $a->id; ?>" data-type="avaliador">Cancelar</button>
                </form>
              </div>
            </div>
            <div class="actions"> 
              <button type="button" onclick='copiarLink(<?php echo json_encode($url); ?>)' class="button">Copiar Link</button>
              <button type="button" class="edit-btn" data-id="<?php echo $a->id; ?>" data-type="avaliador">Editar</button>
              <form method="post" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este avaliador?')">
                <input type="hidden" name="action" value="delete_avaliador">
                <input type="hidden" name="avaliador_id" value="<?php echo $a->id; ?>">
                <?php wp_nonce_field('he_manage_action', 'he_manage_nonce'); ?>
                <button type="submit" class="button-link-delete">Excluir</button>
              </form>
            </div>
          </li>
      <?php
        }
        echo '</ul>';
      } else {
        echo '<p>Nenhum avaliador cadastrado ainda.</p>';
      }
      ?>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
  <script>
   function copiarLink(texto) {
   return navigator.clipboard.writeText(texto)
  }

    document.getElementById('exportPdfBtn').addEventListener('click', () => {
        // NOVO: Defina a URL da sua imagem aqui
        const logoUrl = 'http://localhost/wordpress/wp-content/uploads/2025/06/Marca_IFSP_2015_Car_3-removebg-preview-1.png'; // <-- IMPORTANTE: Troque por a URL real do seu logo

        // 1. Crie um elemento container para o conteúdo do PDF
        const pdfContainer = document.createElement('div');

        // 2. Crie o cabeçalho com a imagem e o título
        // A imagem será colocada dentro de uma div 'pdf-header' para fácil estilização
        pdfContainer.innerHTML = `
            <div class="pdf-header">
                <img src="${logoUrl}" alt="Logo">
            </div>
            <h1>Lista de Avaliadores</h1>
        `;

        // 3. Clone a lista de avaliadores para não mexer na original
        const listToExport = document.querySelector('.he-list').cloneNode(true);
        
        // Remove elementos interativos (botões) do clone
        listToExport.querySelectorAll('.actions').forEach(el => el.remove());
        listToExport.querySelectorAll('.he-edit-form').forEach(el => el.remove());

        // 4. Adicione estilos para formatar o PDF (incluindo o novo cabeçalho)
        const style = document.createElement('style');
        style.innerHTML = `
            body { font-family: sans-serif; }
            /* NOVO: Estilos para o cabeçalho e a imagem */
            .pdf-header {
                text-align: left;
                margin-bottom: 25px;
                border-bottom: 1px solid #eee;
                padding-bottom: 20px;
            }
            .pdf-header img {
                max-width: 150px; /* Ajuste o tamanho máximo do seu logo */
                height: auto;
            }
            h1 { text-align: center; margin-bottom: 24px; color: #333; }
            .he-list { list-style: none; padding: 0; margin: 0; }
            .he-list li { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 15px; 
                border: 1px solid #ccc; 
                border-radius: 8px;
                margin-bottom: 15px; 
                page-break-inside: avoid;
            }
            .item-details { display: flex; align-items: center; gap: 20px; }
            .item-name { font-size: 1.5em; font-weight: bold; }
            .he-link-details { display: flex; align-items: center; gap: 15px; margin-left: 20px; }
            .he-link-details img { width: 100px; height: 100px; }
            .he-link-details p { margin: 0; word-break: break-all; }
            .he-link-details a { text-decoration: none; color: #0073aa; }
        `;
        pdfContainer.appendChild(style);
        pdfContainer.appendChild(listToExport);

        const options = {
            margin: 1,
            filename: 'lista_avaliadores.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 10, useCORS: true }, // useCORS é essencial para carregar a imagem do logo e do QR Code
            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().from(pdfContainer).set(options).save();
    });
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                document.getElementById(`avaliador-details-${id}`).style.display = 'none';
                document.getElementById(`edit-form-avaliador-${id}`).style.display = 'block';
            });
        });

        document.querySelectorAll('.cancel-edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                document.getElementById(`edit-form-avaliador-${id}`).style.display = 'none';
                document.getElementById(`avaliador-details-${id}`).style.display = 'block';
            });
        });
  </script>
<?php
  echo he_get_management_script();
  return ob_get_clean();
}

add_shortcode('he_resultados', 'he_resultados_page');

function he_resultados_page()
{
  global $wpdb;

  echo '<div class="wrap"><h1>Resultados</h1>';
  echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:20px;">';
  echo '<input type="hidden" name="action" value="he_export_results">';
  wp_nonce_field('he_export_results');
  echo '<button type="submit" class="button">Exportar CSV</button>';
  echo '</form>';

  $teams = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}he_times");
  $criteria = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}he_criterios", OBJECT_K);
  $evaluators_count = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}he_avaliadores");

  if (empty($teams) || empty($criteria) || $evaluators_count == 0) {
    echo '<p>Não há dados suficientes para gerar os resultados. Verifique se há times, critérios e avaliações registradas.</p></div>';
    return;
  }

  $results = [];
  foreach ($teams as $team) {
    $total_score = 0;
    $criteria_scores = [];

    foreach ($criteria as $criterion) {
      $avg_star_rating_sql = $wpdb->prepare(
        "SELECT AVG(nota) FROM {$wpdb->prefix}he_avaliacoes WHERE time_id = %d AND criterio_id = %d",
        $team->id,
        $criterion->id
      );
      $avg_star_rating = $wpdb->get_var($avg_star_rating_sql);
      $criterion_points = ($avg_star_rating > 0) ? ($avg_star_rating / 5) * $criterion->peso : 0;
      $criteria_scores[$criterion->id] = $criterion_points;
      $total_score += $criterion_points;
    }

    $results[] = [
      'team_id'         => $team->id,
      'team_name'       => $team->nome,
      'total_score'     => $total_score,
      'criteria_scores' => $criteria_scores,
    ];
  }

  usort($results, function ($a, $b) use ($criteria) {
    if ($a['total_score'] != $b['total_score']) {
      return $a['total_score'] < $b['total_score'] ? 1 : -1;
    }
    $tie_break_criteria_ids = [1, 5, 6, 3];
    foreach ($tie_break_criteria_ids as $crit_id) {
      if (isset($criteria[$crit_id])) {
        $score_a = isset($a['criteria_scores'][$crit_id]) ? $a['criteria_scores'][$crit_id] : 0;
        $score_b = isset($b['criteria_scores'][$crit_id]) ? $b['criteria_scores'][$crit_id] : 0;
        if ($score_a != $score_b) {
          return $score_a < $score_b ? 1 : -1;
        }
      }
    }
    return 0;
  });

?>
  <style>
    .details-row {
      display: none;
    }

    .details-row.show {
      display: table-row;
    }

    .details-cell {
      padding-left: 30px !important;
      background-color: #f9f9f9;
    }

    .details-cell ul {
      margin: 5px 0;
    }
  </style>
  <table class="wp-list-table widefat fixed striped">
    <thead>
      <tr>
        <th style="width: 50px;">Pos.</th>
        <th>Time</th>
        <th style="width: 150px;">Score Total</th>
        <th style="width: 100px;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($results as $index => $res) : ?>
        <tr>
          <td><?php echo $index + 1; ?>º</td>
          <td><?php echo esc_html($res['team_name']); ?></td>
          <td><?php echo number_format($res['total_score'], 2); ?></td>
          <td><button class="button-link" onclick="toggleDetails(<?php echo $res['team_id']; ?>)">Detalhes</button></td>
        </tr>
        <tr class="details-row" id="details-<?php echo $res['team_id']; ?>">
          <td colspan="4" class="details-cell">
            <strong>Pontuação por Critério:</strong>
            <ul>
              <?php foreach ($res['criteria_scores'] as $crit_id => $score): ?>
                <li><?php echo esc_html($criteria[$crit_id]->nome); ?>: <?php echo number_format($score, 2); ?> pontos</li>
              <?php endforeach; ?>
            </ul>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <script>
    function toggleDetails(teamId) {
      const row = document.getElementById('details-' + teamId);
      row.classList.toggle('show');
    }
  </script>
<?php
  echo '</div>';
}

add_action('admin_post_he_export_results',   'he_export_results_handler');
add_action('admin_post_nopriv_he_export_results', 'he_export_results_handler');
function he_export_results_handler()
{
  if (empty($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'he_export_results')) {
    wp_die('Não autorizado');
  }

  global $wpdb;
  $teams = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}he_times");
  $criteria = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}he_criterios", OBJECT_K);

  $results = [];
  foreach ($teams as $team) {
    $total_score = 0;
    $criteria_scores = [];

    foreach ($criteria as $criterion) {
      $avg_star_rating = $wpdb->get_var($wpdb->prepare(
        "SELECT AVG(nota) FROM {$wpdb->prefix}he_avaliacoes WHERE time_id = %d AND criterio_id = %d",
        $team->id,
        $criterion->id
      ));
      $criterion_points = ($avg_star_rating > 0) ? ($avg_star_rating / 5) * $criterion->peso : 0;
      $criteria_scores[$criterion->id] = $criterion_points;
      $total_score += $criterion_points;
    }

    $results[] = [
      'team_id'         => $team->id,
      'team_name'       => $team->nome,
      'total_score'     => $total_score,
      'criteria_scores' => $criteria_scores,
    ];
  }

  usort($results, function ($a, $b) use ($criteria) {
    if ($a['total_score'] != $b['total_score']) {
      return $a['total_score'] < $b['total_score'] ? 1 : -1;
    }
    $tie_break_criteria_ids = [1, 5, 6, 3];
    foreach ($tie_break_criteria_ids as $crit_id) {
      if (isset($criteria[$crit_id])) {
        $score_a = isset($a['criteria_scores'][$crit_id]) ? $a['criteria_scores'][$crit_id] : 0;
        $score_b = isset($b['criteria_scores'][$crit_id]) ? $b['criteria_scores'][$crit_id] : 0;
        if ($score_a != $score_b) {
          return $score_a < $score_b ? 1 : -1;
        }
      }
    }
    return 0;
  });

  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="hackathon_resultados.csv"');
  $out = fopen('php://output', 'w');

  $header = ['Posicao', 'Time', 'Score Total'];
  foreach ($criteria as $c) {
    $header[] = 'Pontos: ' . $c->nome;
  }
  fputcsv($out, $header);

  foreach ($results as $index => $res) {
    $row = [
      $index + 1,
      $res['team_name'],
      number_format($res['total_score'], 2)
    ];
    foreach ($criteria as $c) {
      $row[] = isset($res['criteria_scores'][$c->id]) ? number_format($res['criteria_scores'][$c->id], 2) : '0.00';
    }
    fputcsv($out, $row);
  }

  fclose($out);
  exit;
}

add_shortcode('he_evaluate', 'he_render_evaluation');
function he_render_evaluation()
{
  if (!isset($_GET['token'])) {
    return '<p><strong>Token de avaliação ausente.</strong></p>';
  }
  global $wpdb;
  $token = sanitize_text_field($_GET['token']);
  $evaluator = $wpdb->get_row($wpdb->prepare("SELECT id, nome FROM {$wpdb->prefix}he_avaliadores WHERE token=%s", $token));

  if (!$evaluator) {
    return '<p><strong>Token de avaliação inválido ou não encontrado.</strong></p>';
  }

  $teams = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}he_times ORDER BY nome ASC");
  $criteria = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}he_criterios ORDER BY id ASC");

  if (empty($teams)) {
    return "<p>Ainda não há times cadastrados para avaliar.</p>";
  }
  if (empty($criteria)) {
    return "<p>Ainda não há critérios de avaliação cadastrados.</p>";
  }

  ob_start();
?>
  <style>
    :root {
      --he-primary-color: #4a90e2;
      --he-light-gray: #f0f2f5;
      --he-gray: #ccc;
      --he-dark-gray: #888;
      --he-star-color: #ffd700;
    }

    .he-eval-body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background-color: var(--he-light-gray);
      margin: 0;
      padding: 0;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      box-sizing: border-box;
    }

    .he-eval-container {
      max-width: 100%;
      width: 100%;
      margin: 0;
      overflow-x: hidden;
      flex-grow: 1;
      display: flex;
      align-items: flex-start;
      padding-top: 20px;
    }

    .he-team-track {
      display: flex;
      transition: transform 0.4s ease-in-out;
      will-change: transform;
    }

    .he-team-card {
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      padding: 25px;
      width: 100vw;
      flex-shrink: 0;
      box-sizing: border-box;
    }

    .he-team-card-inner {
      max-width: 500px;
      margin: 0 auto;
    }

    .he-team-card h3 {
      font-size: 1.8em;
      color: var(--he-primary-color);
      text-align: center;
      margin-top: 0;
      margin-bottom: 25px;
    }

    .he-crit {
      margin-bottom: 20px;
    }

    .he-crit strong {
      display: block;
      margin-bottom: 10px;
      color: #333;
      font-size: 1.1em;
    }

    .he-stars {
      display: flex;
      justify-content: center;
      cursor: pointer;
    }

    .star {
      width: 36px;
      height: 36px;
      user-select: none;
      -webkit-user-select: none;
    }

    .star .star-path {
      transition: fill 0.2s, transform 0.2s;
    }

    .star:active .star-path {
      transform: scale(0.9);
    }

    .star-path-full {
      fill: var(--he-star-color);
    }

    .star-path-half {
      fill: url(#grad-half);
    }

    .star-path-empty {
      fill: var(--he-gray);
    }

    .he-nav-wrapper {
      padding: 0 15px;
      max-width: 500px;
      width: 100%;
      margin: 0 auto;
    }

    .he-nav {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 0;
    }

    .he-nav-btn {
      background-color: var(--he-primary-color);
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 8px;
      font-size: 1em;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .he-nav-btn:disabled {
      background-color: var(--he-gray);
      cursor: not-allowed;
    }

    .he-progress-bar {
      width: 100%;
      background-color: var(--he-gray);
      border-radius: 5px;
      height: 10px;
      margin: 10px 0;
    }

    #he-progress {
      width: 0%;
      background-color: var(--he-primary-color);
      border-radius: 5px;
      height: 100%;
      transition: width 0.4s ease;
    }

    .he-status {
      text-align: center;
      color: var(--he-dark-gray);
      height: 20px;
      font-style: italic;
      transition: opacity 0.3s;
    }

    @media (max-width: 480px) {
      .he-team-card h3 {
        font-size: 1.5em;
        margin-bottom: 20px;
      }

      .he-crit strong {
        font-size: 1em;
      }

      .star {
        width: 32px;
        height: 32px;
      }

      .he-nav-btn {
        padding: 10px 15px;
        font-size: 0.9em;
      }
    }
  </style>

  <svg style="width:0;height:0;position:absolute;">
    <defs>
      <linearGradient id="grad-half" x1="0%" y1="0%" x2="100%" y2="0%">
        <stop offset="50%" style="stop-color:var(--he-star-color);" />
        <stop offset="50%" style="stop-color:var(--he-gray);" />
      </linearGradient>
    </defs>
  </svg>

  <div class="he-eval-body">
    <div class="he-eval-container">
      <div class="he-team-track">
        <?php foreach ($teams as $t) : ?>
          <div class="he-team-card" data-team-id="<?php echo $t->id; ?>">
            <div class="he-team-card-inner">
              <h3><?php echo esc_html($t->nome); ?></h3>
              <?php foreach ($criteria as $c) :
                $prev_note = $wpdb->get_var($wpdb->prepare(
                  "SELECT nota FROM {$wpdb->prefix}he_avaliacoes WHERE avaliador_id=%d AND time_id=%d AND criterio_id=%d",
                  $evaluator->id,
                  $t->id,
                  $c->id
                ));
                $rating = floatval($prev_note);
              ?>
                <div class='he-crit'>
                  <strong><?php echo esc_html($c->nome); ?>:</strong>
                  <div class='he-stars' data-crit-id='<?php echo $c->id; ?>' data-rating='<?php echo $rating; ?>'>
                    <?php for ($i = 1; $i <= 5; $i++) :
                      $fill_class = 'star-path-empty';
                      if ($rating >= $i) {
                        $fill_class = 'star-path-full';
                      } elseif ($rating >= $i - 0.5) {
                        $fill_class = 'star-path-half';
                      }
                    ?>
                      <svg class="star" data-value="<?php echo $i; ?>" viewBox="0 0 24 24">
                        <path class="star-path <?php echo $fill_class; ?>" d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"></path>
                      </svg>
                    <?php endfor; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="he-nav-wrapper">
      <div id="he-progress-container">
        <div class="he-progress-bar">
          <div id="he-progress"></div>
        </div>
        <div class="he-status" id="he-save-status"></div>
      </div>
      <div class="he-nav">
        <button id="he-prev-btn" class="he-nav-btn" disabled>Anterior</button>
        <span id="he-counter">Time 1 de <?php echo count($teams); ?></span>
        <button id="he-next-btn" class="he-nav-btn">Próximo</button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const track = document.querySelector('.he-team-track');
      if (!track) return;

      const teams = track.querySelectorAll('.he-team-card');
      if (teams.length === 0) return;

      const nextBtn = document.getElementById('he-next-btn');
      const prevBtn = document.getElementById('he-prev-btn');
      const counterEl = document.getElementById('he-counter');
      const progressBar = document.getElementById('he-progress');
      const statusEl = document.getElementById('he-save-status');

      let currentIndex = 0;
      const totalTeams = teams.length;

      function updateNav() {
        const cardWidth = teams[0].offsetWidth;
        const offset = currentIndex * -cardWidth;
        track.style.transform = `translateX(${offset}px)`;

        counterEl.textContent = `Time ${currentIndex + 1} de ${totalTeams}`;
        progressBar.style.width = `${((currentIndex + 1) / totalTeams) * 100}%`;
        prevBtn.disabled = currentIndex === 0;
        nextBtn.disabled = currentIndex === totalTeams - 1;
      }

      window.addEventListener('resize', updateNav);

      nextBtn.addEventListener('click', () => {
        if (currentIndex < totalTeams - 1) {
          currentIndex++;
          updateNav();
        }
      });

      prevBtn.addEventListener('click', () => {
        if (currentIndex > 0) {
          currentIndex--;
          updateNav();
        }
      });

      function updateStars(starContainer, rating) {
        starContainer.dataset.rating = rating;
        const stars = starContainer.querySelectorAll('.star');
        stars.forEach(star => {
          const starValue = parseFloat(star.dataset.value);
          const path = star.querySelector('.star-path');
          path.classList.remove('star-path-full', 'star-path-half', 'star-path-empty');

          if (rating >= starValue) {
            path.classList.add('star-path-full');
          } else if (rating >= starValue - 0.5) {
            path.classList.add('star-path-half');
          } else {
            path.classList.add('star-path-empty');
          }
        });
      }

      document.querySelectorAll('.he-stars').forEach(starContainer => {
        starContainer.addEventListener('click', e => {
          const clickedStar = e.target.closest('.star');
          if (!clickedStar) return;

          const clickedValue = parseFloat(clickedStar.dataset.value);
          const currentRating = parseFloat(starContainer.dataset.rating);
          let newRating;

          if (currentRating === clickedValue) {
            newRating = clickedValue - 0.5;
          } else if (currentRating === clickedValue - 0.5) {
            newRating = 0;
          } else {
            newRating = clickedValue;
          }

          updateStars(starContainer, newRating);

          const teamId = starContainer.closest('.he-team-card').dataset.teamId;
          const critId = starContainer.dataset.critId;
          saveEvaluation(teamId, critId, newRating);
        });
      });

      let saveTimeout;

      function saveEvaluation(teamId, critId, note) {
        statusEl.textContent = 'Salvando...';
        statusEl.style.opacity = '1';
        clearTimeout(saveTimeout);

        const token = '<?php echo esc_js($token); ?>';
        const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        const url = `${ajaxUrl}?action=he_save&token=${token}&time=${teamId}&crit=${critId}&nota=${note}`;

        fetch(url)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              statusEl.textContent = 'Salvo!';
            } else {
              statusEl.textContent = 'Erro ao salvar.';
              console.error('Falha ao salvar a avaliação:', data);
            }
          })
          .catch(error => {
            statusEl.textContent = 'Erro de conexão.';
            console.error('Erro na requisição:', error);
          })
          .finally(() => {
            saveTimeout = setTimeout(() => {
              statusEl.style.opacity = '0';
            }, 2000);
          });
      }

      updateNav();
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
    wp_send_json_error(['message' => 'Parâmetros ausentes.']);
  }
  $token = sanitize_text_field($_GET['token']);
  $time  = intval($_GET['time']);
  $crit  = intval($_GET['crit']);
  $nota  = floatval($_GET['nota']);

  if ($nota < 0 || $nota > 5) {
    wp_send_json_error(['message' => 'Nota inválida.']);
  }

  $avaliador_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}he_avaliadores WHERE token=%s", $token));
  if (!$avaliador_id) {
    wp_send_json_error(['message' => 'Token inválido.']);
  }

  $result = $wpdb->replace(
    "{$wpdb->prefix}he_avaliacoes",
    ['avaliador_id' => $avaliador_id, 'time_id' => $time, 'criterio_id' => $crit, 'nota' => $nota],
    ['%d', '%d', '%d', '%f']
  );
  if (false === $result) {
    wp_send_json_error(['message' => 'Erro no banco de dados.', 'db_error' => $wpdb->last_error]);
  }
  wp_send_json_success(['message' => 'Salvo com sucesso.']);
}
