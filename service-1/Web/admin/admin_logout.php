<?php

include '../user/config.php';

session_start();//bat dau phien lam viec
session_unset();// xoa tat ca bien trong phien lam viec
session_destroy();// huy phien lam viec hien tai

header('location: ../login.php');// chuyen huong den trang login.php

?>