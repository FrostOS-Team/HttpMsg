<?php
// Wasp Messenger Server Script by timia2109 and MultHub   (New server for HttpMsg)


$action = split('/',$_SERVER["path_info"]);
$folder = '/home/var/www/lewislovesgames/HttpMsg/';
$tokenFile = $folder.'tokens.json';
define("client", array("waspCraft:1.0"));

function randomString($length = 20) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!§$%&/()=?:;';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function lua($data) {
  $lua_table = "{";
  foreach ($data as $index => $value) {
        $lua_table .= (is_numeric($index) ? "[".$index."]=" : "[\"".$index."\"]=");
        if (is_array($value)) {
          $lua_table .= serialize_to_lua($value);
        }
        else if (is_bool($value)) {
          $lua_table .= ($value ? "true" : "false");
        }
        else if (is_numeric($value)) {
          $lua_table .= $value;
        }
        else {
          $lua_table .= "\"".$value."\"";
        }
        $lua_table .= ",";
  }
  $lua_table .= "}";
  return $lua_table;
}

function error($pErr) {
	global client;
	switch ($pErr) {
		case 'uTaken':
			echo lua(array("Error","Username is already taken"));
			break;
		case 'passwd':
			echo lua(array("Error","Password is wrong"));
			break;
		case 'uFalse':
			echo lua(array("Error","Username didnt exists"));
			break;
		case 'client':
			echo lua(array("Error","Wrong Client"));
			break;
		case 'token':
			echo lua(array("Error","Wrong token!"));
			break;			
	}
	exit;
}

function store($data,$username) {
	global $folder;
	file_put_contents($folder.$username, json_encode($data));
}

function get($username,$token = 0) {
	global $folder;
	if (!checkUser($username)) { error('uFalse'); }
	
	$tmp = json_decode(file_get_contents($folder.$username));
	if ($tmp["token"] == $token or $token == 0) { return $tmp; }
	else { error("token"); }
}

function checkUser($uname) {
	global $folder;
	return file_exists($folder.$uname);
}

function makeRead($pString,$pLen) {
	if (strlen($pString) > $pLen) {
		return substr($pString, 0, $pLen-3).'...';
	}
	else {
		return $pString;
	}
}

if (in_array(get_browser() , client ) ) {	error("client"); }

switch ($action[1]) {
	case 'newAcc':
		//Require posts: Name, Username, Mail, Password
		if (checkUser($_POST["username"])) { error("uTaken"); }
		$aData = array();
		$aData["password"] = $_POST['password'];
		$aData["name"] = $_POST['name'];
		$aData["mail"] = $_POST['mail'];
		$aData["messages"] = array();
		$aData["messages"]["__unread__"] = array();
		$aData["token"] = randomString();
		$aData["status"] = 'Hey there, I\'m using Wasp!';
		store($aData,$_POST["username"]);
		echo lua(array('Success',["token"] => $token,));
		break;
		
	case 'getToken':
		//Require Username,Password
		if (!checkUser($_POST["username"])) { error('uFalse'); }
		$aData = get($_POST["username"]);
		if ($_POST["password"] == $aData["password"])
		{ echo lua(array('Succsess',["token"] => $aData["token"])); }
		else 
		{ error("passwd"); }
		break;
		
	case 'getMessages':
		//Require username,token,xLen
		$aData = get($_POST["username"],$_POST["token"]);
		$tmp = array();
		foreach ($aData["messages"] as $key => $value) {
			$tmp[] = array($key,makeRead(array_pop($value),$_POST['xLen']));
		}
		echo lua($tmp);
		break;
		
	case 'getConv':
		//Require usename,token,user
		$aData = get($_POST["username"],$_POST["token"]);
		echo lua($aData["messages"][$_POST["user"]]);
		break;
		
	case 'send':
		//Require username,token,user,msg,msgtype
		$from = get($_POST["username"],$_POST["token"]);
		$to = get($_POST["user"]);
		
		if (!isset($to["messages"][$_POST["username"]])) {
			$to["messages"][$_POST["username"]] = array();
		}
		$to["messages"][$_POST["username"]][] = array($_POST["msg"],$_POST["msgtype"],time(),false);
		$to["messages"]["__unread__"][] = $_POST["username"];
		
		if (!isset($from["messages"][$_POST["user"]])) {
			$from["messages"][$_POST["user"]] = array();
		}
		$from["messages"][$_POST["user"]][] = array($_POST["msg"],$_POST["msgtype"],time(),true);
		
		store($to,$_POST["user"]);
		store($from,$_POST["username"]);
		echo "true";
		break;
		
	case 'getProfile':
		//Require user
		$dat = get($_POST["user"]);
		echo lua(array(
			["status"] => $dat["status"],
			["name"] => $dat["name"],
		));	
		break;
		
	case 'setProfile':
		//Require username,token
		$aData = get($_POST["username"],$_POST["token"]);
		if (isset($_POST["status"])) { $aData["status"] = $_POST["status"]; }
		if (isset($_POST["name"])) { $aData["name"] = $_POST["name"]; }
		store($aData,$_POST["name"]);
		echo "true";
		break;
	
	case 'markAsRead':
		//Require username,token,user
		$aData = get($_POST["username"],$_POST["token"]);
		$t = array_search($_POST["user"], $aData["messages"]["__unread__"]);
		$aData["messages"]["__unread__"][$t] = null;
		store($aData,$_POST["username"]);
		echo "true";
		break;
		
	case 'newMessages':
		//Require username,token
		$aData = get($_POST["username"],$_POST["token"]);
		echo lua($aData["messages"]["__unread__"]);
		break;
		
	case 'ping':
		echo 'true';
		break;

	case 'deleteConv':
		//Require username,token,user
		$aData = get($_POST["username"],$_POST["token"]);
		$aData["messages"][$_POST["user"]] = null;
		store($aData,$_POST["username"]);
		echo "true";
		break;

	case 'newToken':
		$aData = get($_POST["username"],$_POST["token"]);
		$aData["token"] = randomString();
		echo lua(array(
			true,
			["token"] => $aData["token"]
			));
		store($aData,$_POST["username"]);
		break;

	case 'newPassword':
		//Require Username,token,oldPass,newPass
		$aData = get($_POST["username"],$_POST["token"]);
		if ($_POST["oldPass"] == $aData["password"])
		{ 
			$aData["password"] = $_POST["newPass"];
			$aData["token"] = randomString();
			echo lua(array(
				true,
				["token"] => $aData["token"]
			));
			store($aData,$_POST["username"]);
		}
		else
		{ error('passwd'); }
		break;

}
