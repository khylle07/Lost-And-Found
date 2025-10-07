<?php
session_start();
session_destroy();
echo "Session reset. <a href='index.php'>Go to Login</a>";
?>