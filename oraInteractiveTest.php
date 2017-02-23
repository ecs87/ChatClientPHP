<?php
function base64url_encode($data) {
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64UrlDecode($data) {
  return base64_decode(strtr($data, '-_', '+/'));
}
function ecs87_verify_token($connection) {
	if ($_SERVER['HTTP_AUTHORIZATION']) {
		$tokendata = ($_SERVER['HTTP_AUTHORIZATION']);
		$tokenRegex = '/\.([^\.]*?)\./'; // text between periods excluding periods
		preg_match($tokenRegex, $tokendata, $tokenData);
		$tokendata = base64UrlDecode("$tokenData[1]");
		$tokenArrays = json_decode($tokendata, TRUE);
		$tokenArraysEmail = $tokenArrays['data']['useremail'];
		//var_dump($tokenArrays['data']['id']);
		$verified = false;
		if($connection === false) { echo 'Registration/Login Failed. Please try again later.'; }
		$sql = "SELECT * FROM ecs87_chat_users WHERE useremail = ('$tokenArraysEmail')";
		$result = $connection->query($sql);
		while($row = $result->fetch_assoc()) {
			if ($tokenArrays['data']['useremail'] == $row['useremail']) {
				$verified = true;
				break;
			}
		}
	}
	if ($verified == true) {
		return true;
	}
	else {
		return false;
	}
}
function ecs87_chatLogin($connection) {
	if (ecs87_verify_token($connection) == true) {
		return 'Already logged in.';
	}
	else {
		$verified = false;
		if($connection === false) { echo 'Registration/Login Failed. Please try again later.'; }
		$checkUserEmail = $_POST['useremail'];
		//$sql = "SELECT id FROM ecs87_chat_users WHERE ($key) = ('$value')";
		$sql = "SELECT * FROM ecs87_chat_users WHERE useremail = ('$checkUserEmail')";
		$result = $connection->query($sql);
		while($row = $result->fetch_assoc()) {
			if ($_POST['useremail'] == $row['useremail'] && $_POST['password'] == $row['password']) {
				$verified = true;
				break;
			}
		}
		if ($verified == true) {
			//build the headers
			$headers = ['alg'=>'HS256','typ'=>'JWT'];
			$headers_encoded = base64url_encode(json_encode($headers));

			//build the payload
			$payload = ['data' => ['id'=> $row['id'],'useremail'=> $row['useremail']]];//, 'password'=> $row['password']]];
			$payload_encoded = base64url_encode(json_encode($payload));

			//build the signature
			$key = 'secret';
			$signature = hash_hmac('SHA256',"$headers_encoded.$payload_encoded",$key,true);
			$signature_encoded = base64url_encode($signature);

			//build and return the token
			$token = "$headers_encoded.$payload_encoded.$signature_encoded";
			header('Authorization: Bearer '.$token);
			//return $token;
			$tokendata = ($token);
			$tokenRegex = '/\.([^\.]*?)\./'; // text between periods excluding periods
			preg_match($tokenRegex, $tokendata, $tokenData);
			$tokendata = base64UrlDecode("$tokenData[1]");
			$tokenArrays = json_decode($tokendata, TRUE);
			$tokenEmail = $tokenArrays['data']['useremail'];
			$sql = "SELECT id, name, useremail FROM ecs87_chat_users WHERE useremail = ('$tokenEmail')";
			$result = $connection->query($sql);
			while($row = $result->fetch_assoc()) {
				$jsonUserData['data'] = $row;
			}
			return json_encode($jsonUserData);
		}
		else if ($verified == false) {
			return "Login Failed";
		}
	}
}
function ecs87_chat_logout($connection) {
	if (ecs87_verify_token($connection) == true) {
		if ($_SERVER['HTTP_AUTHORIZATION']) {
			unset($_SERVER['HTTP_AUTHORIZATION']);
			//return 'Logged out.';
		}
		else {
			return "Not currently logged in.";
		}
	}
	else {
		return "Bad token.";
	}
}
function ecs87_chat_register($connection) {
	if (ecs87_verify_token($connection) == true) {
		echo 'Already registered/logged in.';
	}
	else {
		ecs87_chat_db_function($connection);
	}
}
function ecs87_chat_readProfile($connection) {
	if (ecs87_verify_token($connection) == true) {
		if ($_SERVER['HTTP_AUTHORIZATION']) {
			ecs87_chat_db_function($connection);
		}
		else {
			return 'Please sign in first.';
		}
	}
	else {
		return "Bad token.";
	}
}
function ecs87_chat_updateProfile($connection) {
	if (ecs87_verify_token($connection) == true) {
		if ($_SERVER['HTTP_AUTHORIZATION']) {
			ecs87_chat_db_function($connection);
		}
		else {
			return 'Please sign in first.';
		}
	}
	else {
		return "Bad token.";
	}
}
function ecs87_chat_db_function($connection) {
	if($connection === false) { return 'Registration/Login Failed. Please try again later.'; }
	else {
		$sql = "CREATE TABLE ecs87_chat_users ( id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, reg_date TIMESTAMP )";
		$connection->query($sql);
		if ($_POST['create-register']) {
			$i = 0;
			foreach ($_POST as $key => $value) {
				if ($key == "create-register") {
					continue;
				}
				$sql = "ALTER TABLE ecs87_chat_users ADD $key text";
				$connection->query($sql);
				if ($i == 0) {
					//determine if the registration failed or not
					$sql = "SELECT * FROM ecs87_chat_users WHERE ($key) = ('$value')"; $result = $connection->query($sql);
					while($row = $result->fetch_assoc()) {
						$failed_register = $row['id'];
						$json_data = $row;
					}
					if ($failed_register) { break; }
					//insert first value into the table for the user/id
					$sql = "INSERT INTO ecs87_chat_users ($key) VALUES ('$value')";
					$connection->query($sql);
					//get the id from the row created
					$sql = "SELECT id FROM ecs87_chat_users WHERE ($key) = ('$value')";
					$result = $connection->query($sql);
					while($row = $result->fetch_assoc()) {
					  $unique_id = $row['id'];
					}
				}
				if ($i > 0) {
					$sql = "UPDATE ecs87_chat_users SET $key = '$value' WHERE id = '$unique_id'";
					$result = $connection->query($sql);
				}
				$i++;
			}
			$sql = "SELECT name, useremail, password, password_confirmation FROM ecs87_chat_users WHERE id = ('$unique_id')"; $result = $connection->query($sql);
			while($row = $result->fetch_assoc()) {
				$jsonUserData[] = $row;
			}
			/* Purge unsuccessful attempts (NULL == email already exists)
			$sql = "SELECT * FROM ecs87_chat_users WHERE useremail IS NULL";
			$result = $connection->query($sql);
			while($row = $result->fetch_assoc()) {
			  $failed_register = $row['id'];
			  $sql = "DELETE FROM ecs87_chat_users WHERE id = '$failed_register'";
				$connection->query($sql);
			}
			*/
			if ($failed_register == true) {
				echo 'That email is already registered, please sign in.';
			}
			else {
				print_r(json_encode($jsonUserData));
			}
			$connection->close();
		}
		else if ($_POST['read-profile']) {
			$tokendata = ($_SERVER['HTTP_AUTHORIZATION']);
			$tokenRegex = '/\.([^\.]*?)\./'; // text between periods excluding periods
			preg_match($tokenRegex, $tokendata, $tokenData);
			$tokendata = base64UrlDecode("$tokenData[1]");
			$tokenArrays = json_decode($tokendata, TRUE);
			$tokenEmail = $tokenArrays['data']['useremail'];
			$sql = "SELECT id,name,useremail FROM ecs87_chat_users WHERE useremail = ('$tokenEmail')";
			$result = $connection->query($sql);
			while($row = $result->fetch_assoc()) {
				$jsonUserData['data'] = $row;
			}
			echo json_encode($jsonUserData);
		}
		else if ($_POST['update-profile']) {
			$tokendata = ($_SERVER['HTTP_AUTHORIZATION']);
			$tokenRegex = '/\.([^\.]*?)\./'; // text between periods excluding periods
			preg_match($tokenRegex, $tokendata, $tokenData);
			$tokendata = base64UrlDecode("$tokenData[1]");
			$tokenArrays = json_decode($tokendata, TRUE);
			$uid = $tokenArrays['data']['id'];
			//$tokenArrays['data']['email']
			$sql = "SELECT * FROM ecs87_chat_users WHERE id = ('$uid')";
			$result = $connection->query($sql);
			while($row = $result->fetch_assoc()) {
				if ($row) {
					foreach ($_POST as $key => $value) {
						if ($key == "update-profile" || $key == "token") {
							continue;
						}
						//$sql = "ALTER TABLE ecs87_chat_users ADD $key text"; $connection->query($sql);
						$sql = "UPDATE ecs87_chat_users SET $key = '$value' WHERE id = '$uid'";
						$connection->query($sql);
					}
				}
			}
			$sql = "SELECT id,name,useremail FROM ecs87_chat_users WHERE id = ('$uid')";
			$result = $connection->query($sql);
			while($row = $result->fetch_assoc()) {
				$jsonUserData['data'] = $row;
			}
			echo json_encode($jsonUserData);
			$connection->close();
		}
	}
}
//db setup
$connection = mysqli_connect('localhost','imeiclea','gsmecs_8705','imeiclea_larvel');
//create/register
if ($_POST['create-register']) {
	echo ecs87_chat_register($connection);
}
//view/read own profile
else if ($_SERVER['HTTP_AUTHORIZATION'] && $_POST['read-profile']) {
	echo ecs87_chat_readProfile($connection);
}
//update profile
else if ($_SERVER['HTTP_AUTHORIZATION'] && $_POST['update-profile']) {
	echo ecs87_chat_updateProfile($connection);
}
//this function should return an HTTP header without an auth bearer key. The client should then erase it's bearer key 
//(client code should look like: if API returns HTTP header without bearer key then unset client bearer key).
else if ($_SERVER['HTTP_AUTHORIZATION'] && $_POST['logout']) {
	echo ecs87_chat_logout($connection);
}
//this verifies the email/username and password against the database, then returns a JWT to the client
else if (!$_SERVER['HTTP_AUTHORIZATION'] && $_POST['password']) {
	echo ecs87_chatLogin($connection);
}
if ($_SERVER['HTTP_AUTHORIZATION']) {
	header('Authorization: '.$_SERVER['HTTP_AUTHORIZATION']);
}
?>