<?php

define("URL_GN", "https://go.gerencianet.com.br");

function gerencianet_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Gerencianet"),
     "token" => array("FriendlyName" => "Token", "Type" => "text", "Size" => "60", ),
     "testmode" => array("FriendlyName" => "Módulo de teste", "Type" => "yesno", "Description" => "Marque esta opção para usar os webservices de teste da Gerencianet"),
    );
	return $configarray;
}

function gerencianet_link($params) {

	#Return if is not in complete screen. This is to prevent duplicate ws requests
	if($_GET['a'] == 'checkout') return;

	# Gateway Specific Variables
	$gatewaytoken = $params['token'];
	$gatewaytestmode = $params['testmode'];

	# Invoice Variables
	$invoiceid = (string)$params['invoiceid'];
	$description = $params["description"];
    // $amount = (int)($params['amount'] * 100);
	# Client Variables
	$fullname = $params['clientdetails']['fullname'];
	$email = $params['clientdetails']['email'];
	$phone = $params['clientdetails']['phonenumber'];

	$request_address = "n";

	# System Variables
	$returnurl = $params['returnurl'];

	# Data to submit to gerencianet
	$data = array();
	$data['itens'] = array();
	// $data['itens'][] = array('itemValor' => $amount, 'itemDescricao' => $description);

	//data - itens
	$table = "tblinvoiceitems";
	$fields = "description,amount";
	$where = array(
		"invoiceid"=> $invoiceid,
	);
	$sort = "id";
	$sortorder = "ASC";
	$limits = "";
	$result = select_query($table,$fields,$where,$sort,$sortorder,$limits);

	while ($service = mysql_fetch_array($result)) {
		$item = array('itemDescricao' => $service['description'],
						'itemValor' => (int)($service['amount'] * 100),
						'itemQuantidade' => 1);
		$data['itens'][] = $item;
	}

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