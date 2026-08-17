<?php
/*
===========================================================
LICENSE GUARD
===========================================================

This file protects individual application PHP files from
being opened directly without a valid license.

It uses the same USER_ID and license_check.php as index.php.

IMPORTANT:
For a new customer/project, normally change ONLY:
    $user_id = "USER001";
===========================================================
*/

$user_id = "USER001";

$license_url =
    "http://localhost/xampp_license_v2/license_check.php";


/* Send license request */

$post_data = http_build_query([
    "user_id" => $user_id
]);

$ch = curl_init($license_url);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);

if ($response === false) {

    $error = curl_error($ch);

    curl_close($ch);

    die(
        "<h2 style='color:red;text-align:center;margin-top:50px;'>"
        . "APPLICATION DISABLED"
        . "</h2>"
        . "<p style='text-align:center;'>"
        . "License server could not be contacted."
        . "</p>"
    );
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);


/* Check HTTP response */

if ($http_code < 200 || $http_code >= 300) {

    die(
        "<h2 style='color:red;text-align:center;margin-top:50px;'>"
        . "APPLICATION DISABLED"
        . "</h2>"
        . "<p style='text-align:center;'>"
        . "License checker returned HTTP "
        . htmlspecialchars((string)$http_code)
        . "</p>"
    );
}


/* Decode license response */

$license = json_decode($response, true);

if (!is_array($license)) {

    die(
        "<h2 style='color:red;text-align:center;margin-top:50px;'>"
        . "APPLICATION DISABLED"
        . "</h2>"
        . "<p style='text-align:center;'>"
        . "Invalid response from license server."
        . "</p>"
    );
}


/* Check license status */

if (
    !isset($license["status"]) ||
    $license["status"] !== "ON"
) {

    $message =
        $license["message"]
        ?? "Application is not authorized.";

    die(
        "<h2 style='color:red;text-align:center;margin-top:50px;'>"
        . "APPLICATION DISABLED"
        . "</h2>"
        . "<p style='text-align:center;'>"
        . htmlspecialchars($message)
        . "</p>"
    );
}


/*
===========================================================
LICENSE VALID

The protected PHP file is now allowed to continue.
===========================================================
*/
?>
