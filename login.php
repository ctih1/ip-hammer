<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    setcookie("Authorization", hash("sha256", $_POST["password"]."ipbanner"));
    header("Location: /index.php");
    die();
}
?>

<html>
    <head>
        <title>login</title>
    </head>
    
    <body>
        <h1>Please login before continuing</h1>
        <form action="login.php" method="POST">
            <label for="password">Password: <label>
            <input id="password" type="password" name="password" placeholder="password">
        </form>
    </body>
</html>