<?php

/*
Plugin Name: Santo do Dia
Plugin URI: https://fellipesoares.com.br/wp-santo-do-dia
Description: Exiba o Santo do dia através de um shortcode [santododia]
Version: 2.0.8
Author: Fellipe Soares
Author URI: https://fellipesoares.com.br
License: GPL2
*/

// Os dados dos santos serão utilizados do site catolicoapp.com
// Eles serão obtidos através de JSON (https://catolicoapp.com/wp-json/wp/v2/santos)
// Exemplo de chamada: https://catolicoapp.com/wp-json/wp/v2/santos?dia=3&mes=1

// Durante a instalação do plugin, crio uma tabela para armazenar os dados do santo do dia,  evitando muitas requisições ao site santo.app.br
// Os campos que deverão ser criados: id, dia, mes, nome, URL da imagem e URL do santo.app.br

// Função para criar a tabela no banco de dados
function santo_do_dia_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . "santo_do_dia";
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        dia tinyint(2) NOT NULL,
        mes tinyint(2) NOT NULL,
        nome varchar(255) NOT NULL,
        imagem varchar(255) NOT NULL,
        url varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Executo a função para obter os dados do santo do dia
    santo_do_dia_obter_dados();
    
    if (!wp_next_scheduled('santo_do_dia_cron')) {
        wp_schedule_event(time(), 'hourly', 'santo_do_dia_cron');
    }
}

register_activation_hook( __FILE__, 'santo_do_dia_install' );

// Função para obter os dados do santo do dia e gravar na tabela
function santo_do_dia_obter_dados() {

    // Defino o timezone para Americas/Sao_Paulo
    date_default_timezone_set('America/Sao_Paulo');

    global $wpdb;
    $table_name = $wpdb->prefix . "santo_do_dia";
    $dia = (int) date('d');
    $mes = (int) date('m');
    $url = "https://catolicoapp.com/wp-json/wp/v2/santos?dia=$dia&mes=$mes";

     // Antes de consultar, verifico se já existe um registro na tabela para a data atual
    $santo = $wpdb->get_row("SELECT 1 FROM $table_name WHERE dia = $dia AND mes = $mes");

    // Se o resultado for igual a 1, significa que já existe um registro para a data atual
    // Logo, não é necessário consultar o site santo.app.br
    if ($santo == 1) {
        return;
    } else {
        // Se o resultado for diferente de 1, significa que não existe um registro para a data atual
        // Logo, é necessário consultar o site santo.app.br
        // Antes de consultar, removo todos os registros da tabela
        $wpdb->query("TRUNCATE TABLE $table_name");

        // Inicializa a sessão cURL
        $curl = curl_init();

        // Configura as opções para a requisição cURL
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30, // Tempo limite da requisição em segundos
        ));

        // Realiza a requisição cURL e obtém a resposta
        $json = curl_exec($curl);

        // Verifica se ocorreu algum erro durante a requisição
        if (curl_errno($curl)) {
            // Tratamento de erro, caso ocorra algum problema com a requisição
            // Por exemplo, você pode escrever no log ou executar outras ações adequadas
            // Se ocorrer um erro, a função pode retornar ou lançar uma exceção, de acordo com a necessidade
            return;
        }

        // Fecha a sessão cURL
        curl_close($curl);

        // Decodifica o JSON retornado
        $dados = json_decode($json);

        // Verifica se os dados são válidos antes de prosseguir
        if (!is_array($dados) || empty($dados)) {
            // Se os dados não forem válidos, retorne ou faça o tratamento adequado
            return;
        }

        // Insere os dados obtidos na tabela
        $wpdb->insert($table_name, array(
            'dia' => $dia,
            'mes' => $mes,
            'nome' => $dados[0]->title->rendered,
            'imagem' => $dados[0]->imagem_destacada,
            'url' => $dados[0]->link
        ));
    }
}


add_action('santo_do_dia_cron', 'santo_do_dia_obter_dados' );

// Função para exibir o santo do dia
// Será exibido a imagem do santo, com link para o site santo.app.br
// Abaixo da imagem, será exibido o nome do santo, com link para o site santo.app.br

function santo_do_dia() {

    // Defino o timezone para Americas/Sao_Paulo
    date_default_timezone_set('America/Sao_Paulo');

    global $wpdb;

    $table_name = $wpdb->prefix . "santo_do_dia";
    $dia = (int) date('d');
    $mes = (int) date('m');
    $santo = $wpdb->get_row("SELECT * FROM $table_name WHERE dia = $dia AND mes = $mes");
    
    $html = "<div class='santo-do-dia-container'>";
    $html .= "<h3>Santo do Dia</h3>";
    $html .= "<div class='santo-do-dia-card'>";
    $html .= "<a href='$santo->url' target='_blank'><img src='$santo->imagem' alt='$santo->nome' /></a>";
    $html .= "<p><a href='$santo->url' target='_blank'>$santo->nome</a></p>";
    $html .= "</div>"; // Fecha o div santo-do-dia-card
    $html .= "</div>"; // Fecha o div santo-do-dia-container

    return $html;
}

// Adiciono o shortcode [santododia]
add_shortcode('santododia', 'santo_do_dia');

function callback_for_setting_up_scripts() {
    wp_register_style( 'santododia-style', plugin_dir_url( __FILE__ ) . 'css/santododia.css' );
    wp_enqueue_style( 'santododia-style' );
}

add_action('wp_enqueue_scripts', 'callback_for_setting_up_scripts');

// Função para remover a tabela do banco de dados, caso o plugin seja desinstalado
// Também é necessário remover o agendamento do cron (obter dados)
function santo_do_dia_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . "santo_do_dia";
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
    wp_clear_scheduled_hook('santo_do_dia_cron');
}

register_deactivation_hook( __FILE__, 'santo_do_dia_uninstall' );