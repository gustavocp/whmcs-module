<?php

define("URL_GN", "https://go.gerencianet.com.br");

function gerencianet_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Gerencianet"),
     "token" => array("FriendlyName" => "Token", "Type" => "text", "Size" => "60", ),
     "request_address" => array("FriendlyName" => "Solicitar Endereço de Entrega", "Type" => "yesno", "Description" => "Marque esta opção para solicitar endereço de entrega na tela de pagamento da Gerencianet"), 
     "testmode" => array("FriendlyName" => "Módulo de teste", "Type" => "yesno", "Description" => "Marque esta opção para usar os webservices de teste da Gerencianet"),
    );
	return $configarray;
}

function gerencianet_link($params) {

	#Return if is not in complete screen. This is to prevent duplicate ws requests
	if($_GET['a'] != 'complete') return;

	# Gateway Specific Variables
	$gatewaytoken = $params['token'];
	$gatewaytestmode = $params['testmode'];

	# Invoice Variables
	$invoiceid = (string)$params['invoiceid'];
	$description = $params["description"];
    $amount = (int)($params['amount'] * 100);
	# Client Variables
	$fullname = $params['clientdetails']['fullname'];
	$email = $params['clientdetails']['email'];
	$address2 = $params['clientdetails']['address2'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = $params['clientdetails']['postcode'];
	$phone = $params['clientdetails']['phonenumber'];

	$request_address = ($params['request_address'])? "s": "n";

	# System Variables
	$returnurl = $params['returnurl'];

	# Data to submit to gerencianet
	$data = array();
	$data['itens'] = array();
	$data['itens'][] = array('itemValor' => $amount, 'itemDescricao' => $description);
	$data['tipo'] = "servico";
	$data['solicitarEndereco'] = $request_address;
	$data['retorno'] = array('identificador' => $invoiceid, 
							 'url' => $returnurl,
							 'urlNotificacao' => $params['systemurl']."/modules/gateways/callback/gerencianet.php",
							 );
	$data['cliente'] = array('nome' => $fullname,
							 'email' => $email, 
							 'celular' => $phone
							 );

	if($request_address == 's') {
		$data['cliente']['bairro'] = $address2;
		$data['cliente']['estado'] = $state;
		$data['cliente']['cidade'] = $city;
		$data['cliente']['cep'] = $postcode;
	}
	$json = json_encode($data);

	$postfields = array('token' => $gatewaytoken, 'dados' => $json);

	$url_complement = '';

	# if test is selected then we will try it
	if($gatewaytestmode) $url_complement = '/teste';
	
	$url = URL_GN . $url_complement . "/api/pagamento/json";
	$options = array('CURLOPT_RETURNTRANSFER' => true, 'CURLOPT_MAXREDIRS' => 2, 'CURLOPT_AUTOREFERER' => true , 'CURLOPT_CONNECTTIMEOUT' => 30);
	$response = curlCall($url, $postfields);
	$decoded_response = json_decode($response);

	if($decoded_response->status == 2) {
		$link = $decoded_response->resposta->link;
		$code .= "<a href='{$link}'><h2>Realizar pagamento</h2></a>";
	} else {
		$code = "<h2>Ocorreu um erro ao gerar o pagamento. Por favor, entre em contato com o lojista.</h2>";
	}

	return $code;
}


?>