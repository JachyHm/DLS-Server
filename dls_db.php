<?php
  /**
   * Soubor definice udaju k DB.
   * 
   * PHP version 7.1
   * 
   * @category  DB
   * @package   DB
   * @author    JachyHm <jachym.hurtik@gmail.com>
   * @copyright 2016 - 2020 JachyHm 
   * @license   Free to share
   * @version   CVS: 1
   * @link      dls_db.php
   */

   /* Definice udaju */
   mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
   $host = 'localhost';
   $user = <user>;
   $pass = <password>;
   $db = <database>;
   $mysqli = new mysqli($host, $user, $pass, $db) or die($mysqli->error);
   $mysqli->set_charset("utf8");