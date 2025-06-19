<?php
session_start();
session_unset();
session_destroy();
header("Location: login.html");
exit();
?>

<a href="logout.php" style="color: red; font-weight: bold;">Logout</a>
