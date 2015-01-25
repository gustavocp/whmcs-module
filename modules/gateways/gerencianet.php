<?php

define("URL_GN", "https://go.gerencianet.com.br");

function gerencianet_config() {
    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"Gerencianet"),
     "token" => array("FriendlyName" => "Token", "Type" => "text", "Size" => "60", ),
     "testmode" => array("FriendlyName" => "Módulo de teste", "Type" => "yesno", "Description" => "Marque esta opção para usar os webservices de teste da Gerencianet"),
     "messageCheckout" => array("FriendlyName" => "Message Checkout", "Type" => "textarea", "Size" => "100", ),
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
	
	# Client Variables
	$fullname = $params['clientdetails']['fullname'];
	$email = $params['clientdetails']['email'];
	$phone = $params['clientdetails']['phonenumber'];
	
	# aditional fields (need to create two custom fields, CPF and Data Nascimento
	$cpf = $params['clientdetails']['customfields1'];
	$birthdayDate = $params['clientdetails']['customfields2'];
	$cep = $params['clientdetails']['postcode'];
	$logradouro = $params['clientdetails']['address1'];
	preg_match( '/([0-9]{1,5})/i', $logradouro, $numero);
	$bairro = $params['clientdetails']['address2'];
	$cidade = $params['clientdetails']['city'];
	$estado = $params['clientdetails']['state'];

	$request_address = 's';

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
	
	// If not empty, show aditional fields
	if(!empty($params['messageCheckout'])) { $data['descricao'] = $params['messageCheckout']; }
	
	$data['retorno'] = array('identificador' => $invoiceid,
							 'url' => $returnurl,
							 'urlNotificacao' => $params['systemurl']."/modules/gateways/callback/gerencianet.php",
							 );
	$data['cliente'] = array('nome' => $fullname,
							 'email' => $email,
							 'celular' =>  str_replace(".", "", str_replace("-", "", $phone)),
							 'cpf' => str_replace(".", "", str_replace("-", "", $cpf)),
							 'nascimento' => implode("-",array_reverse(explode("/",$birthdayDate))),
							 'cep' => $cep,
							 'logradouro' => $logradouro,
							 'numero' => $numero[0],
							 //'complemento' => 'bloco 8 casa 103',
							 'bairro' => $bairro,
							 'cidade' => $cidade,
							 'estado' => $estado,
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