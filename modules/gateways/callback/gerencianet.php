<?php

define("URL_GN", "https://go.gerencianet.com.br");

# Required File Includes
include("../../../dbconnect.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewaymodule = "gerencianet";

$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated");

# Receive notification from gerencianet
$notification = $_POST["notificacao"];
$json = json_encode(array("notificacao" => $notification));
$gatewaytoken = $GATEWAY['token'];

$postfields = array('token' => $gatewaytoken, 'dados' => $json);

# Ask gerencianet what has changed
$url = URL_GN  . "/api/notificacao/json";
$options = array('CURLOPT_RETURNTRANSFER' => true, 'CURLOPT_MAXREDIRS' => 2, 'CURLOPT_AUTOREFERER' => true , 'CURLOPT_CONNECTTIMEOUT' => 30);
$response = curlCall($url, $postfields);
$decoded_response = json_decode($response);
$request = $decoded_response->status;

if ($request == 2) {
    # Successful
	$invoiceid = $decoded_response->resposta->identificador;
	$transid = $decoded_response->resposta->transacao;
	$status = $decoded_response->resposta->status;

	$isInvoice = checkCbInvoiceID($invoiceid,$GATEWAY["name"]);

	if($isInvoice) {
		# Update WHMCS' status based on changes in invoice
		if($status == 'pago'){
    		addInvoicePayment($invoiceid,$transid,null,null,$gatewaymodule); #
		} else if ($status == 'cancelado') {
			$table = 'tblinvoices';
			$update = array('status'=> "Cancelled");
			$where = array('id' => $invoiceid, 'paymentmethod' => $gatewaymodule);
			update_query($table, $update, $where);
		} else if ($status == 'vencido') {
			$table = 'tblinvoices';
			$update = array('status'=> "Unpaid");
			$where = array('id' => $invoiceid, 'paymentmethod' => $gatewaymodule);
			update_query($table, $update, $where);
		}

	}else {
		logTransaction($GATEWAY["name"],$_POST,"Unsuccessful");
	}
	logTransaction($GATEWAY["name"],$_POST,"Successful");
} else {
	# Unsuccessful
    logTransaction($GATEWAY["name"],$_POST,"Unsuccessful");
}

?>