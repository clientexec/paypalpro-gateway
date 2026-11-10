<?php
/*****************************************************************************************************/
/* Callback information for PayPal Pro*/
/*****************************************************************************************************/
// $cPlugin->GetPluginVariable takes queries in the form of plugin_[pluginname]_[variable]

//Normal plugin would include gateway class via the line below
//include_once("../../../modules/billing/models/class.gateway.plugin.php");
//calling from the plugin directory so I do not need to go up in dir

$plugin = 'paypalpro';
$prefix = 'plugin_'.$plugin.'_';

if (!$this->settings->get($prefix.'In Signup') && !$this->settings->get($prefix.'One-Time Payments') && !$this->settings->get($prefix.'Available in Profile Billing Tab')) {
    $fraudStatus = StatusAliasGateway::getInstance($this->user)->getUserStatusIdsFor(array(USER_STATUS_FRAUD));
    $query  = "SELECT COUNT(*) FROM `users` WHERE `paymenttype` = ? AND `status` NOT IN (".implode(', ', $fraudStatus).") AND `groupid` NOT IN (SELECT `id` FROM `groups` WHERE `isadmin` = 1)";
    $result = $this->db->query($query, $plugin);
    list($count) = $result->fetch();

    if ($count == 0) {
        //Plugin not in use
        return;
    }
}

require_once 'modules/billing/models/class.gateway.plugin.php';

$paypalpro["ack"] = urldecode(strtoupper($resArray["ACK"]));

//Create plug in class to interact with CE
//Pass Plugin Name..  will be same name as folder under plugins/gateways
$cPlugin = new Plugin($params["invoiceNumber"],"paypalpro",$this->user);

if ($transType == "charge") {
	$pwpptransid = urldecode($resArray["TRANSACTIONID"]);
	$paypalpro['totalAmount'] = urldecode($resArray["AMT"]);
}
elseif ($transType == "refund") {
	$pwpptransid = urldecode($resArray["REFUNDTRANSACTIONID"]);
	$paypalpro['totalAmount'] = urldecode($resArray["GROSSREFUNDAMT"]);
}

if ($paypalpro['ack']=="SUCCESS" || $paypalpro['ack']=="SUCCESSWITHWARNING") { 
	$responsetext = "$pwpptransid - $transType approved";
}

elseif ($paypalpro['ack']=="FAILURE" || $paypalpro['ack']=="FAILUREWITHWARNING") { 
	$ErrorCode = urldecode($resArray["L_ERRORCODE0"]);
	$ErrorLongMsg = urldecode($resArray["L_LONGMESSAGE0"]);
	$responsetext = "$pwpptransid - $transType declined - $ErrorCode: $ErrorLongMsg"; 
}

elseif ($paypalro["ack"]=="") { $responsetext = "Server Configuration Error: No Response Received"; }

$cPlugin->setAmount($paypalpro['totalAmount']);
$cPlugin->m_TransactionID = $pwpptransid;
$cPlugin->m_Action = ($transType == 'credit')? 'refund' : $transType;
$cPlugin->m_Last4 = mb_substr($params['userCCNumber'],-4);

//check to see if transaction was accepted
if ($paypalpro['ack']=="SUCCESS" || $paypalpro['ack']=="SUCCESSWITHWARNING") {
    $cPlugin->PaymentAccepted($paypalpro['totalAmount'],$responsetext);
}
else { 
	if(!$bolInSignup) {
	$cPlugin->PaymentRejected($responsetext);
	}
}
?>