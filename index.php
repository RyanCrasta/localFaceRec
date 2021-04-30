
<?php
	session_start();

	$_SESSION['key'] = "votingmadeeasy";

?>

<!DOCTYPE html>
<html>
<head>
	<title></title>

	<script type="text/javascript">
			
		function passValue(){

		var email = document.getElementById('txt').value;
		localStorage.setItem("emailValue", email);
		return false;

		}


	</script>
</head>
<body>

	<form  action="next.php">
		<input type="text" name="" id="txt" required>
		<input type="submit" name="" value="Click" onclick="passValue();">
	</form>
</body>
</html>

