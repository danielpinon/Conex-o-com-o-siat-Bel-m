
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
    

if(!isset($_GET['estado_emissor'])){
    header('Content-Type: application/json');
    echo file_get_contents('./estados.json'); 
    exit;
}else{
    $estado_emissor = $_GET['estado_emissor'];
}

if(!isset($_GET['nome'])){
    echo '{"status":"Error","line":27}';
    exit;
}else{
    $nome = $_GET['nome'];
}

if(!isset($_GET['mae'])){
    echo '{"status":"Error","line":34}';
    exit;
}else{
    $mae = $_GET['mae'];
}

if(!isset($_GET['estado'])){
    echo '{"status":"Error","line":41}';
    exit;
}else{
    $estado = $_GET['estado'];
}

if(!isset($_GET['cidade'])){
    echo '{"status":"Error","line":48}';
    exit;
}else{
    $cidade = $_GET['cidade'];
}

if(!isset($_GET['rg'])){
    echo '{"status":"Error","line":55}';
    exit;
}else{
    $rg = $_GET['rg'];
}

if(!isset($_GET['cpf'])){
    echo '{"status":"Error","line":62}';
    exit;
}else{
    $cpf = $_GET['cpf'];
}

if(!isset($_GET['dt_nascimento'])){
    echo '{"status":"Error","line":69}';
    exit;
}else{
    $dt_nascimento = $_GET['dt_nascimento'];
}
try{
    /*
        Set initial url
    */
    $startUrl = 'https://antecedentes.policiacivil.pa.gov.br/consulta';
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
    $page = $session->getPage();
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