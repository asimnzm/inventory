<?php 


function slug($text){ 

  // replace non letter or digits by -
  $text = preg_replace('~[^pLd]+~u', '-', $text);

  // trim
  $text = trim($text, '-');

  // transliterate
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

  // lowercase
  $text = strtolower($text);

  // remove unwanted characters
  $text = preg_replace('~[^-w]+~', '', $text);

  if (empty($text))
  {
    return 'n-a';
  }

  return $text;
}

//////////////////


function getCurrentUri()
	{
		$basepath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
		$uri = substr($_SERVER['REQUEST_URI'], strlen($basepath));
		if (strstr($uri, '?')) $uri = substr($uri, 0, strpos($uri, '?'));
		$uri = '/' . trim($uri, '/');
		return $uri;
	}


///////////////////////////
function createDir($path){		
	if (!file_exists($path)) {
		$old_mask = umask(0);
		mkdir($path, 0777, TRUE);
		umask($old_mask);
	}
}





////////////////////////////////

function createThumb($path1, $path2, $file_type, $new_w, $new_h, $squareSize = ''){
	/* read the source image */
	$source_image = FALSE;
	
	if (preg_match("/jpg|JPG|jpeg|JPEG/", $file_type)) {
		$source_image = imagecreatefromjpeg($path1);
	}
	elseif (preg_match("/png|PNG/", $file_type)) {
		
		if (!$source_image = @imagecreatefrompng($path1)) {
			$source_image = imagecreatefromjpeg($path1);
		}
	}
	elseif (preg_match("/gif|GIF/", $file_type)) {
		$source_image = imagecreatefromgif($path1);
	}		
	if ($source_image == FALSE) {
		$source_image = imagecreatefromjpeg($path1);
	}

	$orig_w = imageSX($source_image);
	$orig_h = imageSY($source_image);
	
	if ($orig_w < $new_w && $orig_h < $new_h) {
		$desired_width = $orig_w;
		$desired_height = $orig_h;
	} else {
		$scale = min($new_w / $orig_w, $new_h / $orig_h);
		$desired_width = ceil($scale * $orig_w);
		$desired_height = ceil($scale * $orig_h);
	}
			
	if ($squareSize != '') {
		$desired_width = $desired_height = $squareSize;
	}

	/* create a new, "virtual" image */
	$virtual_image = imagecreatetruecolor($desired_width, $desired_height);
	// for PNG background white----------->
	$kek = imagecolorallocate($virtual_image, 255, 255, 255);
	imagefill($virtual_image, 0, 0, $kek);
	
	if ($squareSize == '') {
		/* copy source image at a resized size */
		imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $orig_w, $orig_h);
	} else {
		$wm = $orig_w / $squareSize;
		$hm = $orig_h / $squareSize;
		$h_height = $squareSize / 2;
		$w_height = $squareSize / 2;
		
		if ($orig_w > $orig_h) {
			$adjusted_width = $orig_w / $hm;
			$half_width = $adjusted_width / 2;
			$int_width = $half_width - $w_height;
			imagecopyresampled($virtual_image, $source_image, -$int_width, 0, 0, 0, $adjusted_width, $squareSize, $orig_w, $orig_h);
		}

		elseif (($orig_w <= $orig_h)) {
			$adjusted_height = $orig_h / $wm;
			$half_height = $adjusted_height / 2;
			imagecopyresampled($virtual_image, $source_image, 0,0, 0, 0, $squareSize, $adjusted_height, $orig_w, $orig_h);
		} else {
			imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $squareSize, $squareSize, $orig_w, $orig_h);
		}
	}
	
	if (@imagejpeg($virtual_image, $path2, 90)) {
		imagedestroy($virtual_image);
		imagedestroy($source_image);
		return TRUE;
	} else {
		return FALSE;
	}
}	




//////////////

/*******************************************************************************************************************/


//function gets access token from PayPal
function apiContext(){
	$apiContext = new PayPal\Rest\ApiContext(new PayPal\Auth\OAuthTokenCredential(CLIENT_ID, CLIENT_SECRET));
	return $apiContext;
}

//create PayPal payment method
function create_paypal_payment($total, $currency, $desc, $my_items, $redirect_url, $cancel_url){
	$redirectUrls = new PayPal\Api\RedirectUrls();
	$redirectUrls->setReturnUrl($redirect_url);
	$redirectUrls->setCancelUrl($cancel_url);
	
	$payer = new PayPal\Api\Payer();
	$payer->setPaymentMethod("paypal");
	
	$amount = new PayPal\Api\Amount();
	$amount->setCurrency($currency);
	$amount->setTotal($total);
									
	$items = new PayPal\Api\ItemList();
	$items->setItems($my_items);
		
	$transaction = new PayPal\Api\Transaction();
	$transaction->setAmount($amount);
	$transaction->setDescription($desc);
	$transaction->setItemList($items);

	$payment = new PayPal\Api\Payment();
	$payment->setRedirectUrls($redirectUrls);
	$payment->setIntent("sale");
	$payment->setPayer($payer);
	$payment->setTransactions(array($transaction));
	
	$payment->create(apiContext());
	
	return $payment;
}

//executes PayPal payment
function execute_payment($payment_id, $payer_id){
	$payment = PayPal\Api\Payment::get($payment_id, apiContext());
	$payment_execution = new PayPal\Api\PaymentExecution();
	$payment_execution->setPayerId($payer_id);	
	$payment = $payment->execute($payment_execution, apiContext());	
	return $payment;
}


//pay with credit card
function pay_direct_with_credit_card($credit_card_params, $currency, $amount_total, $my_items, $payment_desc) {		
	
	$card = new PayPal\Api\CreditCard();
	$card->setType($credit_card_params['type']);
	$card->setNumber($credit_card_params['number']);
	$card->setExpireMonth($credit_card_params['expire_month']);
	$card->setExpireYear($credit_card_params['expire_year']);
	$card->setCvv2($credit_card_params['cvv2']);
	$card->setFirstName($credit_card_params['first_name']);
	$card->setLastName($credit_card_params['last_name']);
	
	$funding_instrument = new PayPal\Api\FundingInstrument();
	$funding_instrument->setCreditCard($card);

	$payer = new PayPal\Api\Payer();
	$payer->setPayment_method("credit_card");
	$payer->setFundingInstruments(array($funding_instrument));
	
	$amount = new PayPal\Api\Amount();
	$amount->setCurrency($currency);
	$amount->setTotal($amount_total);
	
	$transaction = new PayPal\Api\Transaction();
	$transaction->setAmount($amount);
	$transaction->setDescription("creating a direct payment with credit card");
	
	$payment = new PayPal\Api\Payment();
	$payment->setIntent("sale");
	$payment->setPayer($payer);
	$payment->setTransactions(array($transaction));

	$payment->create(apiContext());	
	
	return $payment;
}

////////////////////////////////////////////////
function submenu_generate($hmber_smenu,$cats){
///////////////////////////////////////////////////
$hmb_menu   =  unserialize($hmber_smenu);
$new_hmenu = array();
for($j=0; $j< count($hmb_menu);$j++){
$new_hmenu[] =  '<li><a style="font-size:12px;"  href="page.php?p='.$cats->getCatSlugHm($hmb_menu[$j]).'">'.$cats->getCatTitleHm($hmb_menu[$j]).'</a></li>';
}
return  $new_hmenu;
////////////////////////////////////////

}

#-#############################################
# desc: creates an <select> box
# param: name, array['val']="display" of data, default selected, extra parameters
# returns: html of box and options
function create_selectbox($name, $data, $default='', $param=''){
    $out='<select id="'.$name.'"  name="'.$name.'"'. (!empty($param)?' '.$param:'') .">\n";

    foreach($data as $key=>$val) {
        $out.='<option value="' .$key. '"'. ($default==$key?' selected="selected"':'') .'>';
        $out.=$val;
        $out.="</option>\n";
    }
    $out.="</select>\n";

    return $out;
}#-# create_selectbox()


function current_dropdown_unserialz($hmber_smenu){
///////////////////////////////////////////////////
$new_hmber_smenu = array();
foreach($hmber_smenu as $crow) {
$new_hmber_smenu[] = unserialize($crow['hcat_cat_id']);
}

/////////////////////////////////////////////////////
$hmb_menu   =  $new_hmber_smenu;
$new_hmenu = array();
for($j=0; $j< count($hmb_menu);$j++){
$new_hmenu[] =  $hmb_menu[$j];
}

 return $new_hmenu;
////////////////////////////////////////

}


/************************** delete folder and files *********************************************/
function recursive_remove_directory($directory, $empty=FALSE)
{
     if(substr($directory,-1) == '/')
     {
        $directory = substr($directory,0,-1);
     }
     if(!file_exists($directory) || !is_dir($directory))
     {
        return FALSE;
     }elseif(is_readable($directory))
     {
        $handle = opendir($directory);
         while (FALSE !== ($item = readdir($handle)))
         {
            if($item != '.' && $item != '..')
            {
                 $path = $directory.'/'.$item;
                 if(is_dir($path)) 
                {
                     recursive_remove_directory($path);
               }else{
                    unlink($path);
                }
            }
        }
        closedir($handle);
        if($empty == FALSE)
       {
           if(!rmdir($directory))
             {
                 return FALSE;
             }
         }
     }
     return TRUE;
 }

/////////////////// limit string add dots ////////////////////////////////////////////////////

function add3dots($string, $repl, $limit) 
{
  if(strlen($string) > $limit) 
  {
    return substr($string, 0, $limit) . $repl; 
  }
  else 
  {
    return $string;
  }
}



/********************************************************************************************************************/













/*******************************************************************************************************************/
?>