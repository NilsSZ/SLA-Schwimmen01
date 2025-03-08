<?php
/**
 * license_helper.php
 *
 * Enthält Funktionen zur Lizenzprüfung für Module.
 * Du kannst das Mapping anpassen, um jedem Modul eine spezifische ID zuzuordnen.
 */

// Globales Mapping: Passe hier die Modul-IDs an, wie Du es möchtest.
$moduleMapping = [
    'attest'            => 1,
    'daten_hinzufuegen' => 6,
    'online_shop'       => 3,
    'livetiming'        => 4,
    // Füge weitere Module hinzu, z. B.:
    // 'meine_zeiten'   => 5,
    // 'trainingsplan'  => 6,
];

function checkLicense($moduleKey, $userId) {
    global $moduleMapping;
    if (!isset($moduleMapping[$moduleKey])) {
        return false;  // Modul nicht konfiguriert
    }
    $module_id = $moduleMapping[$moduleKey];
    
    require_once 'dbconnection.php';
    $stmt = $conn->prepare("SELECT id, license_code, invoice_pdf, purchase_date FROM licenses WHERE module_id = ? AND user_id = ? AND active = 1");
    $stmt->bind_param("ii", $module_id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $license = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $license ? $license : false;
}
function checkLicenseByModuleId($module_id, $user_id) {
    require_once 'dbconnection.php';
    $stmt = $conn->prepare("SELECT id, license_code, invoice_pdf, purchase_date FROM licenses WHERE module_id = ? AND user_id = ? AND active = 1");
    $stmt->bind_param("ii", $module_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $license = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return $license ? $license : false;
}
?>
