<?php
session_start();

if(isset($_GET['closeAccess'])){
    unset($_SESSION['authenticated']);
    session_destroy();
    session_unset();
    header('location: index');
}
?>