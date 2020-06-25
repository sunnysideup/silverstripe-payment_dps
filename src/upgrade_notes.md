2020-06-26 10:51

# running php upgrade inspect see: https://github.com/silverstripe/silverstripe-upgrader
cd /var/www/upgrades/payment_dps
php /var/www/upgrader/vendor/silverstripe/upgrader/bin/upgrade-code inspect /var/www/upgrades/payment_dps/payment_dps/src  --root-dir=/var/www/upgrades/payment_dps --write -vvv
Array
(
    [0] => Running post-upgrade on "/var/www/upgrades/payment_dps/payment_dps/src"
    [1] => [2020-06-26 10:51:20] Applying ApiChangeWarningsRule to DpsPxPayment_Handler.php...
    [2] => PHP Fatal error:  Cannot declare class Sunnysideup\PaymentDps\Control\DpsPxPayPayment_Handler, because the name is already in use in /var/www/upgrades/payment_dps/payment_dps/src/Control/DpsPxPayment_Handler.php on line 67
)


------------------------------------------------------------------------
To continue, please use the following parameter: startFrom=InspectAPIChanges-1
e.g. php runme.php startFrom=InspectAPIChanges-1
------------------------------------------------------------------------
            