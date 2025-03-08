<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
// stripe_checkout.php

// Stripe-Bibliothek manuell einbinden
require_once '/kunden/homepages/4/d1007545866/htdocs/SLA-Projekt/stripe-php-master/init.php';

// Stripe API-Schlüssel setzen (verwende Deinen Test-Schlüssel)
\Stripe\Stripe::setApiKey('sk_test_51Qp9j0RsaJvBoRs2uDPbKySkvspNKaTFE0SynxY6CZuU7FX0Y1T4xtfZKgnRzgUGnMyyIVDlawxyJ7vNDlkboxeZ00UVYQXeD7');

session_start();

// Parameter aus dem Formular (z. B. aus der Produktdetailseite) abrufen
if (!isset($_POST['module_id']) || !isset($_POST['purchase_type'])) {
    header("Location: online-shop.php");
    exit;
}

$module_id    = (int) $_POST['module_id'];
$purchaseType = $_POST['purchase_type']; // "self" oder "gift"
$giftEmail    = isset($_POST['gift_email']) ? trim($_POST['gift_email']) : null;

// Modul aus der Datenbank abrufen – passe die SQL-Abfrage an Deine DB-Struktur an
require_once 'dbconnection.php';
$stmt = $conn->prepare("SELECT name, price, description FROM modules WHERE id = ?");
$stmt->bind_param("i", $module_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Modul nicht gefunden.");
}
$module = $result->fetch_assoc();
$stmt->close();
$conn->close();

$productName  = $module['name'];
$productPrice = $module['price']; // Preis in Euro
$productDesc  = $module['description'];

// Stripe verlangt den Betrag in den kleinsten Währungseinheiten (z. B. Cent)
$amount = $productPrice * 100;

try {
    // Erstelle eine Stripe Checkout Session
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency'     => 'eur',
                'product_data' => [
                    'name'        => $productName,
                    'description' => $productDesc,
                ],
                'unit_amount'  => $amount,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        // Erfolg- und Abbruch-URLs (Passe diese URLs an Deine Domain an)
        'success_url' => 'https://sla-schwimmen.de/sla-projekt/module/success.php?session_id={CHECKOUT_SESSION_ID}&purchase_type=' . urlencode($purchaseType) . '&module_id=' . $module_id . ($purchaseType === 'gift' ? "&gift_email=" . urlencode($giftEmail) : ""),
        'cancel_url'  => 'https://sla-schwimmen.de/sla-projekt/module/cancel.php',
    ]);
    
    // Weiterleiten zur Stripe Checkout-Seite
    header("Location: " . $session->url);
    exit;
} catch (Exception $e) {
    // Fehlerbehandlung
    echo "Fehler bei der Erstellung der Zahlungs-Session: " . $e->getMessage();
    exit;
}
?>
