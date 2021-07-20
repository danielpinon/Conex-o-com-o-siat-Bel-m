
<?php
/* Informa o nível dos erros que serão exibidos */
error_reporting(E_ALL);

/* Habilita a exibição de erros */
ini_set("display_errors", 1);

require_once 'vendor/autoload.php';
/*
    Change Classes
*/
use Behat\Mink\Mink,
    Behat\Mink\Session,
    Behat\Mink\Driver\GoutteDriver,
    Behat\Mink\Driver\Goutte\Client as GoutteClient;
    
try{
    /*
        Set initial url
    */
    $startUrl = 'http://siat.belem.pa.gov.br:8081/acesso/pages/geral/home.jsf';
    /*
        Create classes in variables
    */
    $client = new GoutteClient();
    $client->setClient(new \GuzzleHttp\Client(array(
        'verify' => false,
    )));
    
    $driver = new GoutteDriver($client);
    $session = new Session($driver);
    /*
        Init browser
    */
    $session->start(['verify' => false]);
    $session->visit($startUrl);
    $cookie = $session->getCookie('JSESSIONID');
    $page = $session->getPage();
    // Autentica
    $autoriza = autorize($cookie);
    // Redireciona o usuário para a tela inicial
    $session->visit($startUrl);
    // Obtem o link da página de notas
    $page = $session->getPage();
    $link = $page->findAll('css','a.ui-menuitem-link.ui-corner-all');
    $url = $link[3]->getAttribute('href');
    // Abre a nova página
    $session->visit($url);
    // Notas fiscais lançadas
    if(isset($_GET['extrato'])){
        // Abre a pagina de consultar notas
        $session->visit("http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/consultaNFSe/consultaNFSeFiltro.jsf");
        $page = $session->getPage();
        $array = $page->findAll('css','form')[2]->findAll('css','input');
        $newArray = [];
        foreach ($array as $row) {
            $newArray[$row->getAttribute('name')] = $row->getAttribute('value');
        }
        /**
         * Pega informações do sistema no filtro
         * Início = 01/01/2020
         * Final = Data Atual
         */
        if(isset($_GET['datainicio'])){
            $dataInicial = $_GET['datainicio'];
        }else{
            $dataInicial = '01/01/2020';
        }

        if(isset($_GET['datafinal'])){
            $datafinal = $_GET['datafinal'];
        }else{
            $datafinal = date('d/m/Y');
        }
        $notas = obterNotasFiscaisLancadas($dataInicial, $datafinal, $session->getCookie('JSESSIONID'), $newArray);
        echo json_encode($notas);
        exit;
    }else if(isset($_GET['newnota'])){
        // Abre a pagina de emitir notas
        $newArray = [];
        $session->visit("http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf");
        $page = $session->getPage();
        $form = $page->findAll('css','form')[2];
        $array = $form->findAll('css','input');
        $button = $form->findAll('css','a')[2];
        /**
         * Obtem imputs do passo 1
         */
        foreach ($array as $row) {
            $newArray[$row->getAttribute('name')] = $row->getAttribute('value');
        }
        emitirNotaPasso1($newArray,$session->getCookie('JSESSIONID'));
        dd($newArray);


        $form->submit();
        // Próxima pagina
        $form = $page->findAll('css','form')[2];
        $array = $form->findAll('css','input');
        foreach ($array as $row) {
            $newArray[$row->getAttribute('name')] = $row->getAttribute('value');
        }
        dd($newArray);
    }
    
    echo $page->getContent();
    exit;
    dd($page->getContent());
    $paginaAtual = $session->getCurrentUrl();
    // Aguarda 5 segundos
    dd($paginaAtual, $session->getResponseHeaders());
    dd($cpf);
    dd($page);
    /*
        Login in page
    */
    $form = $page->find('css','.container > form');
    $input = $page->findField('nome');
    $input->setValue($nome);
    $input = $page->findField('mae');
    $input->setValue($mae);
    if(isset($_GET['pai'])){
        $pai = $_GET['pai'];
        $input = $page->findField('pai');
        $input->setValue($pai);
    }
    $input = $page->findField('estado');
    $input->setValue($estado);
    $input = $page->findField('cidade');
    $input->setValue($cidade);
    $input = $page->findField('rg');
    $input->setValue($rg);
    $input = $page->findField('estado_emissor');
    $input->setValue($estado_emissor);
    $input = $page->findField('cpf');
    $input->setValue($cpf);
    $input = $page->findField('dt_nascimento');
    $input->setValue($dt_nascimento);
    $form->submit();
    $form = $page->find('css','.container > form');
    $form->submit();
    // retrieving response headers:
    $jsonHead = $session->getResponseHeaders();
    //print_r(json_encode($jsonHead));
    header('Content-Disposition: inline; filename="antecedente.pdf"');
    header('Content-Type: application/pdf');
    echo $page->getContent();
    
} catch (Exception $e) {
    dd($e);
    echo '{"status":"Error","line":126}';
}

/**
 * Autorização para liberar sessão
 */
function autorize($cookie)
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_PORT => "8081",
        CURLOPT_URL => "http://siat.belem.pa.gov.br:8081/acesso/j_spring_security_check",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "j_username=02310995258&j_password=b69laOh-OgRW8nKPy9qJ2w&j_vinculante=37371821000144&javax.faces.ViewState=4643493206215931317%5E%25%5E3A-5038278673120715916&javax.faces.ViewState=4643493206215931317%5E%25%5E3A-5038278673120715916&javax.faces.ViewState=4643493206215931317%5E%25%5E3A-5038278673120715916&javax.faces.partial.ajax=true&javax.faces.partial.ajax=true&javax.faces.source=userNameCpf&javax.faces.source=j_idt40&javax.faces.partial.execute=userNameCpf%2Blogin-form-2%2Bfrag1%2Bfrag2%2Bfrag0a&javax.faces.partial.execute=login-form-2%2BfragBtt_1&javax.faces.partial.render=login-form-2%2Bfrag1%2Bfrag2%2BredSenha&javax.faces.partial.render=fragBtt_2%2Bpassword%2BfragBtt_1&javax.faces.behavior.event=valueChange&javax.faces.partial.event=change&userNameCpf=023.109.952-58&userNameCpf=023.109.952-58&login-form-2=login-form-2&login-form-2=login-form-2&password=&password=&password=danDAN8060&txtEm=&txtEm=&txtCe=&txtCe=&j_idt40=j_idt40&vinculante_focus=&vinculante_input=37.371.821%5E%25%5E2F0001-44",
        CURLOPT_HTTPHEADER => [
        "Accept: */*",
        "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
        "Cache-Control: no-cache",
        "Connection: keep-alive",
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
        "Cookie: JSESSIONID=".$cookie,
        "Faces-Request: partial/ajax",
        "Origin: http://siat.belem.pa.gov.br:8081",
        "Pragma: no-cache",
        "Referer: http://siat.belem.pa.gov.br:8081/acesso/pages/geral/home.jsf",
        "Upgrade-Insecure-Requests: 1",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
        "X-Requested-With: XMLHttpRequest",
        "authority: maxcdn.bootstrapcdn.com",
        "cache-control: no-cache",
        "pragma: no-cache",
        "sec-ch-ua: ^\^"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        return $response;
    }
}

/**
 * Função para obter informações de nota fiscal
 */
function obterNotasFiscaisLancadas($dataInit,$dataFinal,$cookie,$body)
{
    // Datas de filtro
    $body["j_id123:j_id164"] = $dataInit;
    $body["j_id123:j_id168"] = $dataFinal;
    $body["j_id123:tipoNota"] = "1";
    $body["j_id123:tipoRecolhimento"] = null;
    $body["j_id123:cboTipoNota"] = null;
    $body["j_id123:cboSituacao"] = null;
    $body["j_id123:cboAtv"] = null;
    $body["j_id123:j_id173"] = "j_id123:j_id173";
    // Inicia a requisição
    $curl = curl_init();
    curl_setopt_array($curl, [
    CURLOPT_PORT => "8180",
    CURLOPT_URL => "http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/consultaNFSe/consultaNFSeFiltro.jsf",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($body)."&AJAX%253AEVENTS_COUNT=1",
    CURLOPT_HTTPHEADER => [
        "Accept: */*",
        "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
        "Cache-Control: no-cache",
        "Connection: keep-alive",
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
        "Cookie: JSESSIONID=".$cookie,
        "Origin: http://siat.nota.belem.pa.gov.br:8180",
        "Pragma: no-cache",
        "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/consultaNFSe/consultaNFSeFiltro.jsf",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
    ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
    echo "cURL Error #:" . $err;
    } else {
        $response = explode('<div class="row">',$response)[9];
        $response = explode('<tr',$response);
        $notas = [];
        foreach ($response as $key => $value) {
            if($key > 1){
                $colunas = explode('<td',$value);
                $notas[$key] = [];
                foreach ($colunas as $key1 => $coluna) {
                    if($key1 == 1){
                        $numeroNota = str_replace('</a></td>','',explode('</span>',$coluna)[3]);
                        $notas[$key]['numero'] = $numeroNota;
                    }else if($key1 == 2){
                        $notas[$key]['tipo'] = limpaDado($coluna);
                    }else if($key1 == 3){
                        $notas[$key]['Emissão'] = limpaDado($coluna);
                    }else if($key1 == 4){
                        $notas[$key]['Tomador'] = limpaDado($coluna);
                    }else if($key1 == 5){
                        $notas[$key]['valorservico'] = limpaDado($coluna);
                    }else if($key1 == 6){
                        $notas[$key]['valornota'] = limpaDado($coluna);
                    }else if($key1 == 7){
                        $notas[$key]['issretido'] = limpaDado($coluna);
                    }else if($key1 == 8){
                        $notas[$key]['tipotributacao'] = limpaDado($coluna);
                    }else if($key1 == 9){
                        $notas[$key]['valoriss'] = limpaDado($coluna);
                    }else if($key1 == 10){
                        $notas[$key]['aliquota'] = limpaDado($coluna);
                    }else if($key1 == 11){
                        $notas[$key]['situacao'] = limpaDado($coluna);
                    }
                }
            }
        }
        return $notas;
    }
}

/**
 * Emitir nota
 */
    /**
     * Passo 1 
     * Informações do Tomador
     */
    function emitirNotaPasso1($body,$cookie)
    {
        $body["form1:cmbApelido"] = "";
        $body["form1:cpfCnpjTomador"] = "09331450206";
        $body["form1:inscricaoMunicipalTomador"] = "";
        $body["form1:razaoSocialTomador"] = "JOSÉ MARCIAL DE BRITO PINON";
        $body["form1:enderecoTomador"] = "AVENIDA AUGUSTO MONTENEGRO";
        $body["form1:bairroTomador"] = "PARQUE VERDE";
        $body["form1:cepTomador"] = "66635110";
        $body["form1:dddTomador"] = "91";
        $body["form1:foneTomador"] = "9983347877";
        $body["form1:emailTomador"] = "jose.jmbp@hotmail.com";
        $body["form1:cmbUfTomador"] = "PA";
        //$body["form1:cmbMunicipioTomador"] = "961";
        //$body["form1:j_id232"] = "";
        $body["form1:j_id548"] = "form1:j_id548";
        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_PORT => "8180",
        CURLOPT_URL => "http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($body)."&AJAX%253AEVENTS_COUNT=1",
        CURLOPT_HTTPHEADER => [
            "Accept: */*",
            "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
            "Cache-Control: no-cache",
            "Connection: keep-alive",
            "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
            "Cookie: JSESSIONID=".$cookie,
            "Origin: http://siat.nota.belem.pa.gov.br:8180",
            "Pragma: no-cache",
            "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
        echo "cURL Error #:" . $err;
        } else {
            echo $response;
            exit;
            dd($response);
        echo $response;
        }
    }

    /**
     * Passo 2
     * Informações da Atividade
     */

    /**
     * Passo 3
     * Informações dos Itens
     */

    /**
     * Passo 4
     * Enviar Nota
     */

/**
 * Funções do sistema
 */
    // Função de limpar dado da nota
    function limpaDado($dado){
        $dado = explode('">',$dado)[1];
        $dado = explode('</td>',$dado)[0];
        return $dado;
    }