
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
    $headers = getRequestHeaders();
    if($headers["Authentication"] != "7y9IR0JtI@IV3JcJ@Nf6wY5AqybRa#mU9TlhhdMy7lO#f2wwpRYOqcBM*$09e6tJgEyDuATG#8"){
        header('Content-Type: application/json');
        echo json_encode(["err"=>true,"msg"=>"Autenticação Falhou!"]);
        exit;
    }
    
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
    // Opções de operações
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
            // Validação de data inicial
            $validacao = validateDate($dataInicial);
            if(!$validacao){
                header('Content-Type: application/json');
                echo json_encode(["err"=>true,"cod"=>"02","msg"=>"Formato de Data Inicial Inválido"]);
                exit;
            }
        }else{
            $dataInicial = '01/01/2020';
        }

        if(isset($_GET['datafinal'])){
            $datafinal = $_GET['datafinal'];
            // Validação de data final
            $validacao = validateDate($datafinal);
            if(!$validacao){
                header('Content-Type: application/json');
                echo json_encode(["err"=>true,"cod"=>"02","msg"=>"Formato de Data Final Inválido"]);
                exit;
            }
        }else{
            $datafinal = date('d/m/Y');
        }
        // Retorna o extrato
        $notas = obterNotasFiscaisLancadas($dataInicial, $datafinal, $session->getCookie('JSESSIONID'), $newArray);
        header('Content-Type: application/json');
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
        /**
         * Obtem informações da nota
         */
        $post = [];
        foreach ($_POST as $key => $value) {
            $post[$key] = $value;
            switch ($key) {
                case 'TOMADOR_CPF_CNPJ':
                    if(strlen($value) != 11){
                        header('Content-Type: application/json');
                        echo json_encode(["err"=>true,"msg"=>"CPF não tem 11 Dígitos"]);
                        exit;
                    }
                    break;
                case 'TOMADOR_CEP':
                    if(strlen($value) != 8){
                        header('Content-Type: application/json');
                        echo json_encode(["err"=>true,"msg"=>"CEP não tem 8 Dígitos"]);
                        exit;
                    }
                    break;
                case 'TOMADOR_DDD':
                    if(strlen($value) != 2){
                        header('Content-Type: application/json');
                        echo json_encode(["err"=>true,"msg"=>"DDD não tem 2 Dígitos"]);
                        exit;
                    }
                    break;
                case 'TOMADOR_TELEFONE':
                    if(strlen($value) != 10){
                        header('Content-Type: application/json');
                        echo json_encode(["err"=>true,"msg"=>"Telefone não tem 10 Dígitos"]);
                        exit;
                    }
                    break;
                case 'TOMADOR_UF':
                    if(strlen($value) != 2){
                        header('Content-Type: application/json');
                        echo json_encode(["err"=>true,"msg"=>"UF não tem 2 Dígitos"]);
                        exit;
                    }
                    break;
            }
        }
        //dd($post);
        /**
         * Inicia Processo de Emissão
         */
        $cookie = $session->getCookie('JSESSIONID');
        $passo1 = emitirNotaPasso1($post,$newArray,$cookie);
        $passo2 = emitirNotaPasso2($passo1,$post,$newArray,$cookie);
        $passo3 = emitirNotaPasso3($passo2,$post,$newArray,$cookie);
        $passo4 = emitirNotaPasso4($passo3,$post,$newArray,$cookie);
        // Obtem número da nota
        $session->visit("http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/envioEmail.jsf");
        $page = $session->getPage();
        $codigo = $page->getContent();
        $limpa = explode("mero da NFSe: ",$page->getContent())[1];
        $limpa = explode("!</span>",$limpa)[0];
        header('Content-Type: application/json');
        echo json_encode(["success"=>true,"msg"=>"Nota Fiscal gerada com sucesso!","numero"=>$limpa]);
        exit;
    }else if(isset($_GET['cancelanota'])){
        // Abre a pagina de cancelar notas
        $newArray = [];
        $session->visit("http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/cancelamentoNFSe/cancelamentoNFSeFiltro.jsf");
        $page = $session->getPage();
        $cookie = $session->getCookie('JSESSIONID');
        $form = $page->findAll('css','form')[2];
        $array = $form->findAll('css','input');
        $button = $form->findAll('css','a')[2];
        /**
         * Obtem imputs do passo 1
         */
        foreach ($array as $row) {
            $newArray[$row->getAttribute('name')] = $row->getAttribute('value');
        }
        // Busca Nota
        $nota = buscaNota($newArray,$cookie);
        // Limpa dados
        if(!isset(explode('<a href="#"',$nota)[1])){
            $informacao = explode('<span class="ui-messages-warn-detail">',$nota)[1];
            $informacao = explode('</span>',$informacao)[0];
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>$informacao]);
            exit;
        }
        $limpa = explode('<a href="#"',$nota)[1];
        $limpa = explode("'idNota':'",$limpa)[1];
        $limpa = explode("','",$limpa)[0];
        // Filtra Nota
        filtraNota($limpa, $newArray,$cookie);
        $session->visit("http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/cancelamentoNFSe/cancelamentoNFSeResultado.jsf");
        $page = $session->getPage();
        /**
         * Obtem a textarea
         */
        $codigo = $page->getContent();
        $limpa = explode('<textarea',$codigo)[1];
        $limpa = explode('</textarea>',$limpa)[0];
        $limpa = explode('readonly="readonly">',$limpa)[1];
        $limpa = html_entity_decode($limpa);
        // Cancela a nota
        cancelarNota($limpa,$cookie);
        $session->visit("http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/util/paginaSucesso.jsf");
        $page = $session->getPage();
        $codigo = $page->getContent();
        $limpa = explode('<div class="page-header">',$codigo)[1];
        $limpa = explode('</h1>',$limpa)[0];
        $limpa = explode('<h1>',$limpa)[1];
        $limpa = html_entity_decode($limpa);
        header('Content-Type: application/json');
        echo json_encode(["success"=>true,"msg"=>$limpa]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(["err"=>true,"msg"=>"Nada por aqui!"]);
    exit;
} catch (Exception $e) {
    echo '{"status":"Error"';
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
    function emitirNotaPasso1($post,$body,$cookie)
    {
        // Sem informações de post
        if(count($post) == 0){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações no POST"]);
            exit;
        }
        // Seleciona Cidade
        selecionaCidade($post,$body,$cookie);
        // Apelido
        $body["form1:cmbApelido"] = "";
        // CPF/CNPJ
        if(!isset($post["TOMADOR_CPF_CNPJ"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de CPF/CNPJ"]);
            exit;
        }else{
            $body["form1:cpfCnpjTomador"] = $post["TOMADOR_CPF_CNPJ"];
        }
        // Inscrição Municipal
        $body["form1:inscricaoMunicipalTomador"] = (!isset($post["TOMADOR_INSCRICAO_MUNICIPAL"]))?"":$post["TOMADOR_INSCRICAO_MUNICIPAL"];
        // Nome/Razão Social
        if(!isset($post["TOMADOR_RAZAO_SOCIAL"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de Razão Social/Nome do Tomador"]);
            exit;
        }else{
            $body["form1:razaoSocialTomador"] = $post["TOMADOR_RAZAO_SOCIAL"];
        }
        // Endereço
        if(!isset($post["TOMADOR_ENDERECO"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de Endereço do Tomador"]);
            exit;
        }else{
            $body["form1:enderecoTomador"] = $post["TOMADOR_ENDERECO"];
        }
        // Bairro Tomador
        if(!isset($post["TOMADOR_BAIRRO"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de Bairro do Tomador"]);
            exit;
        }else{
            $body["form1:bairroTomador"] = $post["TOMADOR_BAIRRO"];
        }
        // Cep Tomador
        if(!isset($post["TOMADOR_CEP"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de Cep do Tomador"]);
            exit;
        }else{
            $body["form1:cepTomador"] = $post["TOMADOR_CEP"];
        }
        // DDD Tomador
        if(!isset($post["TOMADOR_DDD"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de DDD do Tomador"]);
            exit;
        }else{
            $body["form1:dddTomador"] = $post["TOMADOR_DDD"];
        }
        // Telefone Tomador
        if(!isset($post["TOMADOR_TELEFONE"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de Telefone do Tomador"]);
            exit;
        }else{
            $body["form1:foneTomador"] = $post["TOMADOR_TELEFONE"];
        }
        // Telefone Tomador
        if(!isset($post["TOMADOR_EMAIL"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de Email do Tomador"]);
            exit;
        }else{
            $body["form1:emailTomador"] = $post["TOMADOR_EMAIL"];
        }
        // UF Tomador
        if(!isset($post["TOMADOR_UF"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de UF do Tomador"]);
            exit;
        }else{
            $body["form1:cmbUfTomador"] = $post["TOMADOR_UF"];
        }
        // Municipio Tomador
        if(!isset($post["TOMADOR_MUNICIPIO"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Sem informações de Município do Tomador"]);
            exit;
        }else{
            $body["form1:cmbMunicipioTomador"] = $post["TOMADOR_MUNICIPIO"];
        }
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

        return $response;
    }

    

    /**
     * Passo 2
     * Informações da Atividade
     */
    function emitirNotaPasso2($passo1,$post,$body,$cookie){
        $atividade = selecionaAtividade($passo1,$post,$body,$cookie);
        $tributacao = selecionaTributacao($atividade,$post,$body,$cookie);
        $recolhimento = selecionaRecolhimento($tributacao,$post,$body,$cookie);
        $aliquota = selecionaAliquota($recolhimento,$post,$body,$cookie);
        return $aliquota;
    }

    /**
     * Passo 3
     * Informações dos Itens
     */
    
    function emitirNotaPasso3($passo2,$post,$body,$cookie){
        // Adiciona descrição
        if(!isset($post["DESCRICAO_NOTA"])){
            header('Content-Type: application/json');
            echo json_encode(["err"=>true,"msg"=>"Descrição Não Definido"]);
            exit;
        }
        /**
         * Lista os itens
         */
        $itensTotais = 10;
        $itens = [];
        for ($i=1; $i <= 10; $i++) { 
            if(isset($post['ITEM_'.$i.'_DESCRICAO_SERVICO'])){
                $itens[$i]['DESCRICAO'] = $post['ITEM_'.$i.'_DESCRICAO_SERVICO'];
                $itens[$i]['QUANTIDADE'] = $post['ITEM_'.$i.'_QUANTIDADE'];
                $itens[$i]['VALOR'] = $post['ITEM_'.$i.'_VALORUNITARIO'];
            }else{
                break;
            }
        }
        /**
         * Adiciona um a um
         */
        $adicionarItem = null;
        foreach ($itens as $key => $item) {
            if(
                !isset($item['DESCRICAO']) ||
                !isset($item['QUANTIDADE']) ||
                !isset($item['VALOR']) ||
                $item['DESCRICAO'] == "" ||
                $item['QUANTIDADE'] == "" ||
                $item['VALOR'] == ""
            ){
                header('Content-Type: application/json');
                echo json_encode(["err"=>true,"msg"=>"Item $key mal formado!"]);
                exit;
            }
            $adicionarItem = adicionarItens($item,$passo2,$post,$body,$cookie);
        }
        return $adicionarItem;
    }

    /**
     * Passo 4
     * Enviar Nota
     */
    function emitirNotaPasso4($passo3,$post,$body,$cookie){
        $form = [];
        $form["j_id564"] = "j_id564";
        $form['javax.faces.ViewState'] = 'j_id3';
        $form['j_id564:j_id586'] = 'j_id564:j_id586';
        
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
        CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($form)."&AJAX%253AEVENTS_COUNT=1",
        CURLOPT_HTTPHEADER => [
            "Accept: */*",
            "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
            "Cache-Control: no-cache",
            "Connection: keep-alive",
            "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
            "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
            "Origin: http://siat.nota.belem.pa.gov.br:8180",
            "Pragma: no-cache",
            "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
        ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        return $response;
    }

    /**
     * Funções Extras
     */
        /**
         * Passo 01
         */
            // Estado
            function selecionaEstado($post,$body,$cookie){
                // UF Tomador
                if(!isset($post["TOMADOR_UF"])){
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Sem informações de UF do Tomador"]);
                    exit;
                }else{
                    $body["form1:cmbUfTomador"] = $post["TOMADOR_UF"];
                }
                $body["form1:j_id221"] = "form1:j_id221";
                //dd($body);
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
                    "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);
        
                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                return $response;
                //echo $response;
                //exit;
                //dd($response);
            }
            // Cidade
            function selecionaCidade($post,$body,$cookie)
            {
                selecionaEstado($post,$body,$cookie);
                
                unset($body["form1:j_id165"]);
                unset($body["form1:j_id192"]);
                unset($body["form1:j_id229"]);
                unset($body["form1:j_id232"]);
                // UF Tomador
                if(!isset($post["TOMADOR_UF"])){
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Sem informações de UF do Tomador"]);
                    exit;
                }else{
                    $body["form1:cmbUfTomador"] = $post["TOMADOR_UF"];
                }
                // Municipio Tomador
                if(!isset($post["TOMADOR_MUNICIPIO"])){
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Sem informações de Município do Tomador"]);
                    exit;
                }else{
                    $body["form1:cmbMunicipioTomador"] = $post["TOMADOR_MUNICIPIO"];
                }
                $body["form1:j_id229"] = "";
                $body["form1:j_id232"] = "";
                $body["javax.faces.ViewState"] = "j_id3";
                $body["form1:j_id224"] = "form1:j_id224";
                //$body["form1:j_id548"] = "form1:j_id548";
                //dd($body);
                //dd(urlencode(http_build_query($body)));
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
                return $response;

                //dd($response);
                /*if ($err) {
                echo "cURL Error #:" . $err;
                } else {
                    echo $response;
                    exit;
                    dd($response);
                echo $response;
                }*/
            }
        /**
         * Passo 02
         */
            // Atividade
            function selecionaAtividade($passo1,$post,$body,$cookie){
                // Abrindo html
                libxml_use_internal_errors(true);
                $doc = new DOMDocument();
                $passo1 = str_replace(['<?xml version="1.0" standalone="yes"?>','<?xml version="1.0"?>'],'',$passo1);
                $doc->loadHTML($passo1);
                // Atividade da Nota
                if(!isset($post["ATIVIDADE_TIPO"])){
                    $selects = [];
                    $selectn = $doc->getElementsByTagName('select')->length;
                    for ($i=0; $i < $selectn; $i++) { 
                        $select = $doc->getElementsByTagName('select')->item($i);
                        $selects[$select->getAttribute('name')] = [];
                        $options = $select->getElementsByTagName('option');
                        for ($o=0; $o < $options->length; $o++) {
                            $selects[$select->getAttribute('name')][$o]['name'] = $options->item($o)->nodeValue;
                            $selects[$select->getAttribute('name')][$o]['value'] = $options->item($o)->getAttribute('value');
                        }
                    }
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Atividade da Nota Não Definido","lista"=>$selects["form1:cmbAtividades"]]);
                    exit;
                }else{
                    $form = [];
                    $form['form1'] = 'form1';
                    $form["form1:cmbAtividades"] = $post["ATIVIDADE_TIPO"];
                    $form['form1:j_id255'] = '';
                    $form['form1:cmbUfPrestacaoServico'] = ''; 
                    $form['form1:aliquotaIss'] = '';
                    $form['javax.faces.ViewState'] = 'j_id3';
                    $form['form1:j_id242'] = 'form1:j_id242';
                }
                
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
                CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($form)."&AJAX%253AEVENTS_COUNT=1",
                CURLOPT_HTTPHEADER => [
                    "Accept: */*",
                    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
                    "Cache-Control: no-cache",
                    "Connection: keep-alive",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                return $response;
            }
            // Tributação
            function selecionaTributacao($atividade,$post,$body,$cookie){
                // Abrindo html
                libxml_use_internal_errors(true);
                $doc = new DOMDocument();
                $passo1 = str_replace(['<?xml version="1.0" standalone="yes"?>','<?xml version="1.0"?>'],'',$atividade);
                $doc->loadHTML($passo1);
                $selects = [];
                $selectn = $doc->getElementsByTagName('select')->length;
                for ($i=0; $i < $selectn; $i++) { 
                    $select = $doc->getElementsByTagName('select')->item($i);
                    $selects[$select->getAttribute('name')] = [];
                    $options = $select->getElementsByTagName('option');
                    for ($o=0; $o < $options->length; $o++) {
                        $selects[$select->getAttribute('name')][$o]['name'] = $options->item($o)->nodeValue;
                        $selects[$select->getAttribute('name')][$o]['value'] = $options->item($o)->getAttribute('value');
                        $selects[$select->getAttribute('name')][$o]['selected'] = $options->item($o)->getAttribute('selected');
                    }
                }
                // Tributação
                if(!isset($post["ATIVIDADE_TRIBUTACAO"])){
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Tributação Não Definido","lista"=>$selects["form1:cmbTributacao"]]);
                    exit;
                }else{
                    $form = [];
                    $form['form1'] = 'form1';
                    $form["form1:cmbAtividades"] = $post["ATIVIDADE_TIPO"];
                    foreach ($selects["form1:j_id248"] as $value) {
                        if($value['selected'] == 'selected'){
                            $form['form1:j_id248'] = $value['value'];
                            $form['form1:j_id255'] = $value['name'];
                        }
                    }
                    $form['form1:cmbUfPrestacaoServico'] = $post["TOMADOR_UF"]; 
                    $form['form1:cmbMunicipioPrestacaoServico'] = $post["TOMADOR_MUNICIPIO"]; 
                    $form['form1:cmbTributacao'] = $post["ATIVIDADE_TRIBUTACAO"];
                    $form['form1:aliquotaIss'] = '';
                    $form['javax.faces.ViewState'] = 'j_id3';
                    $form['form1:j_id282'] = 'form1:j_id282';
                }
                
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
                CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($form)."&AJAX%253AEVENTS_COUNT=1",
                CURLOPT_HTTPHEADER => [
                    "Accept: */*",
                    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
                    "Cache-Control: no-cache",
                    "Connection: keep-alive",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                return [$response,[$form['form1:j_id248'],$form['form1:j_id255']]];
            }
            // Recolhimento
            function selecionaRecolhimento($atividade,$post,$body,$cookie){
                // Abrindo html
                libxml_use_internal_errors(true);
                $doc = new DOMDocument();
                $passo1 = str_replace(['<?xml version="1.0" standalone="yes"?>','<?xml version="1.0"?>'],'',$atividade[0]);
                $doc->loadHTML($passo1);
                $selects = [];
                $selectn = $doc->getElementsByTagName('select')->length;
                for ($i=0; $i < $selectn; $i++) { 
                    $select = $doc->getElementsByTagName('select')->item($i);
                    $selects[$select->getAttribute('name')] = [];
                    $options = $select->getElementsByTagName('option');
                    for ($o=0; $o < $options->length; $o++) {
                        $selects[$select->getAttribute('name')][$o]['name'] = $options->item($o)->nodeValue;
                        $selects[$select->getAttribute('name')][$o]['value'] = $options->item($o)->getAttribute('value');
                    }
                }
                // Recolhimento
                if(!isset($post["ATIVIDADE_RECOLHIMENTO"])){
                    //dd($selects);
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Recolhimento Não Definido","lista"=>$selects["form1:cmbRecolhimento"]]);
                    exit;
                }else{
                    $form = [];
                    $form['form1'] = 'form1';
                    $form["form1:cmbAtividades"] = $post["ATIVIDADE_TIPO"];
                    $form['form1:j_id248'] = $atividade[1][0];
                    $form['form1:j_id255'] = $atividade[1][1];
                    $form['form1:cmbUfPrestacaoServico'] = $post["TOMADOR_UF"]; 
                    $form['form1:cmbMunicipioPrestacaoServico'] = $post["TOMADOR_MUNICIPIO"]; 
                    $form['form1:cmbTributacao'] = $post["ATIVIDADE_TRIBUTACAO"];
                    $form['form1:cmbRecolhimento'] = $post["ATIVIDADE_RECOLHIMENTO"];
                    $form['form1:aliquotaIss'] = '';
                    $form['javax.faces.ViewState'] = 'j_id3';
                    $form['form1:j_id287'] = 'form1:j_id287';
                }
                
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
                CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($form)."&AJAX%253AEVENTS_COUNT=1",
                CURLOPT_HTTPHEADER => [
                    "Accept: */*",
                    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
                    "Cache-Control: no-cache",
                    "Connection: keep-alive",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                return [$response,[$atividade[1][0],$atividade[1][1]]];
            }
            // Aliquota
            function selecionaAliquota($atividade,$post,$body,$cookie){
                // Abrindo html
                libxml_use_internal_errors(true);
                $doc = new DOMDocument();
                $passo1 = str_replace(['<?xml version="1.0" standalone="yes"?>','<?xml version="1.0"?>'],'',$atividade[0]);
                $doc->loadHTML($passo1);
                // Aliquota
                if(!isset($post["ATIVIDADE_ALIQUOTAISS"])){
                    //dd($selects);
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Aliquota Não Definido"]);
                    exit;
                }else{
                    $form = [];
                    $form['form1'] = 'form1';
                    $form["form1:cmbAtividades"] = $post["ATIVIDADE_TIPO"];
                    $form['form1:j_id248'] = $atividade[1][0];
                    $form['form1:j_id255'] = $atividade[1][1];
                    $form['form1:cmbUfPrestacaoServico'] = $post["TOMADOR_UF"]; 
                    $form['form1:cmbMunicipioPrestacaoServico'] = $post["TOMADOR_MUNICIPIO"]; 
                    $form['form1:cmbTributacao'] = $post["ATIVIDADE_TRIBUTACAO"];
                    $form['form1:cmbRecolhimento'] = $post["ATIVIDADE_RECOLHIMENTO"];
                    $form['form1:aliquotaIss'] = $post["ATIVIDADE_ALIQUOTAISS"];
                    $form['javax.faces.ViewState'] = 'j_id3';
                    $form['form1:j_id548'] = 'form1:j_id548';
                }
                
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
                CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($form)."&AJAX%253AEVENTS_COUNT=1",
                CURLOPT_HTTPHEADER => [
                    "Accept: */*",
                    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
                    "Cache-Control: no-cache",
                    "Connection: keep-alive",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                return $response;
            }
        /**
         * Passo 03
         */
            // Adicionar Itens
            function adicionarItens($item,$passo2,$post,$body,$cookie){
                $form = [];
                $form['form1'] = 'form1';
                $form["form1:informacoesComplementares"] = $post["DESCRICAO_NOTA"];
                $form["form1:j_id439"] = 1;
                $form["form1:descricaoItem"] = $item["DESCRICAO"];
                $form["form1:quantidadeItem"] = $item["QUANTIDADE"];
                $form["form1:valorUnitarioItem"] = $item["VALOR"];
                $form["form1:dgTributos:0:j_id531"] = "0,0000";
                $form["form1:dgTributos:0:j_id535"] = "0,00";
                $form["form1:dgTributos:1:j_id531"] = "0,0000";
                $form["form1:dgTributos:1:j_id535"] = "0,00";
                $form["form1:dgTributos:2:j_id531"] = "0,0000";
                $form["form1:dgTributos:2:j_id535"] = "0,00";
                $form["form1:dgTributos:3:j_id531"] = "0,0000";
                $form["form1:dgTributos:3:j_id535"] = "0,00";
                $form["form1:dgTributos:4:j_id531"] = "0,0000";
                $form["form1:dgTributos:4:j_id535"] = "0,00";
                $form['javax.faces.ViewState'] = 'j_id3';
                $form['form1:j_id450'] = 'form1:j_id450';
                
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
                CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($form)."&AJAX%253AEVENTS_COUNT=1",
                CURLOPT_HTTPHEADER => [
                    "Accept: */*",
                    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
                    "Cache-Control: no-cache",
                    "Connection: keep-alive",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/emissaoNFSe/emissaoNFSeInsercao.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                return $response;
            }
        /**
         * Cancelamento de nota
         */
            // Busca Nota
            function buscaNota($body,$cookie){
                if(!isset($_POST['NOTA_NUMERO'])){
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Número da Nota Não Definido"]);
                    exit;
                }
                $body['j_id123:numeroNota'] = (int)$_POST['NOTA_NUMERO'];
                $body['j_id123:j_id140'] = 'j_id123:j_id140';
                //dd($body);
                $curl = curl_init();

                curl_setopt_array($curl, [
                CURLOPT_PORT => "8180",
                CURLOPT_URL => "http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/cancelamentoNFSe/cancelamentoNFSeFiltro.jsf",
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
                    "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/cancelamentoNFSe/cancelamentoNFSeFiltro.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                return $response;
            }
            // Filtra nota
            function filtraNota($idNota,$body,$cookie){
                $body['j_id123:numeroNota'] = (int)$_POST['NOTA_NUMERO'];
                $body['j_id123:j_id140'] = 'j_id123:j_id140';
                $body['idNota'] = $idNota;
                $body['j_id123:lista:0:btDetalhar'] = 'j_id123:lista:0:btDetalhar';
                $curl = curl_init();

                curl_setopt_array($curl, [
                CURLOPT_PORT => "8180",
                CURLOPT_URL => "http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/cancelamentoNFSe/cancelamentoNFSeFiltro.jsf",
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
                    "Cookie: _ga=GA1.4.1810977608.1627678472; JSESSIONID=".$cookie,
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/cancelamentoNFSe/cancelamentoNFSeFiltro.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                
                curl_close($curl);
                return $response;
            }
            // Cancela a nota
            function cancelarNota($textArea,$cookie){
                if(!isset($_POST['NOTA_DESCRICAO_CANCELAMENTO'])){
                    header('Content-Type: application/json');
                    echo json_encode(["err"=>true,"msg"=>"Descrição de Cancelamento de Nota Não Definido"]);
                    exit;
                }
                $form = [];
                $form['j_id123'] = 'j_id123';
                $form['j_id123:j_id272'] = $textArea;
                $form['j_id123:j_id277'] = $_POST['NOTA_DESCRICAO_CANCELAMENTO'];
                $form['javax.faces.ViewState'] = 'j_id4';
                $form['j_id123:j_id278'] = 'j_id123:j_id278';
                //
                $curl = curl_init();
                curl_setopt_array($curl, [
                CURLOPT_PORT => "8180",
                CURLOPT_URL => "http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/cancelamentoNFSe/cancelamentoNFSeResultado.jsf",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "AJAXREQUEST=_viewRoot&".http_build_query($form)."&AJAX%253AEVENTS_COUNT=1",
                CURLOPT_HTTPHEADER => [
                    "Accept: */*",
                    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
                    "Cache-Control: no-cache",
                    "Connection: keep-alive",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Cookie: JSESSIONID=$cookie",
                    "Origin: http://siat.nota.belem.pa.gov.br:8180",
                    "Pragma: no-cache",
                    "Referer: http://siat.nota.belem.pa.gov.br:8180/sistematributario/jsp/cancelamentoNFSe/cancelamentoNFSeResultado.jsf",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36"
                ],
                ]);
                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);
                //
                return $response;
            }

/**
 * Funções do sistema
 */
    // Função de limpar dado da nota
    function limpaDado($dado){
        $dado = explode('">',$dado)[1];
        $dado = explode('</td>',$dado)[0];
        return $dado;
    }

    // Função de validação de formato de data
    function validateDate($date, $format = 'd/m/Y')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    function getRequestHeaders() {
        $headers = array();
        foreach($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
        return $headers;
    }