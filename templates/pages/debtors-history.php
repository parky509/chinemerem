<?php
/**
 * Debtors History Page Template - COMPLETE REBUILD v3
 * Zero caching, direct database queries
 */

if (!defined('ABSPATH')) {
    exit;
}

// Force fresh page - aggressive no-cache headers
if (!headers_sent()) {
    header('Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0, s-maxage=0, post-check=0, pre-check=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
    header('Vary: *');
}

// Clear any WordPress object cache
wp_cache_flush();

global $wpdb;
$wpdb->flush();  // Clear wpdb query cache

$is_super_admin = CFI_Auth::is_super_admin();
$message = '';
$message_type = '';

// Table names
$trans_table = $wpdb->prefix . 'cfi_debtor_transactions';
$debtors_table = $wpdb->prefix . 'cfi_debtors';
$orders_table = $wpdb->prefix . 'cfi_orders';
$order_items_table = $wpdb->prefix . 'cfi_order_items';

// Handle Delete Action
if (isset($_POST['cfi_delete_trans']) && $is_super_admin && wp_verify_nonce($_POST['cfi_del_nonce'], 'cfi_del_trans')) {
    $trans_id = intval($_POST['trans_id']);
    $result = $wpdb->delete($trans_table, array('id' => $trans_id), array('%d'));
    if ($result) {
        $message = 'Transaction deleted';
        $message_type = 'success';
    } else {
        $message = 'Delete failed';
        $message_type = 'error';
    }
}

// Get selected debtor filter
$selected_debtor = isset($_GET['debtor']) ? intval($_GET['debtor']) : 0;

// Build query with SQL_NO_CACHE for fresh data
$where = '1=1';
$params = array();
if ($selected_debtor) {
    $where .= ' AND dt.debtor_id = %d';
    $params[] = $selected_debtor;
}

$query = "SELECT SQL_NO_CACHE dt.*, d.name as debtor_name, u.display_name as staff_name 
          FROM `{$trans_table}` dt 
          LEFT JOIN `{$debtors_table}` d ON dt.debtor_id = d.id 
          LEFT JOIN `{$wpdb->users}` u ON dt.staff_id = u.ID 
          WHERE {$where} 
          ORDER BY dt.id DESC 
          LIMIT 100";

$history = $params ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

// Get debtors for filter
$debtors = $wpdb->get_results("SELECT * FROM {$debtors_table} WHERE status = 'active' ORDER BY name ASC");

// Generate unique page ID to break caching
$page_uid = substr(md5(microtime(true)), 0, 8);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="page-uid" content="<?php echo $page_uid; ?>">
<title>Debtors History</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;padding:0;background:#f8fafc}
.container{max-width:1200px;margin:0 auto;padding:1rem}
.header{background:linear-gradient(135deg,#001943,#003366);color:#fff;padding:0.75rem 1rem;border-radius:12px;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem}
.header h1{margin:0;font-size:0.85rem;display:flex;align-items:center;gap:0.5rem}
.btn{display:inline-flex;align-items:center;gap:0.4rem;padding:0.5rem 0.75rem;border:none;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;font-size:0.7rem;transition:all 0.2s}
.btn-primary{background:#001943;color:#fff}
.btn-white{background:#fff;color:#001943}
.glass{background:rgba(255,255,255,0.95);border-radius:12px;padding:1.5rem;box-shadow:0 4px 20px rgba(0,25,67,0.1);border:2px solid rgba(0,25,67,0.1);margin-bottom:1.5rem}
.filters{display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;margin-bottom:1.5rem}
.filter-group{flex:1;min-width:150px}
.filter-group label{display:block;margin-bottom:0.5rem;font-weight:600;color:#001943;font-size:0.85rem}
.select{width:100%;padding:0.6rem;border:2px solid #e2e8f0;border-radius:8px;font-size:0.75rem}
table{width:100%;border-collapse:collapse;font-size:0.8rem}
th{background:#001943;color:#fff;padding:0.6rem 0.4rem;text-align:left;font-size:0.75rem}
td{padding:0.5rem 0.4rem;border-bottom:1px solid #e2e8f0}
tr:hover{background:rgba(0,25,67,0.02)}
.badge{display:inline-flex;align-items:center;gap:0.25rem;padding:0.2rem 0.5rem;border-radius:20px;font-size:0.65rem;font-weight:600}
.badge-order{background:#fee2e2;color:#991b1b}
.badge-payment{background:#dcfce7;color:#166534}
.badge-initial{background:#dbeafe;color:#1e40af}
.badge-adjustment{background:#fef3c7;color:#92400e}
.empty{text-align:center;padding:3rem;color:#64748b}
.empty i{font-size:3rem;margin-bottom:1rem;display:block}
.action-btn{padding:0.25rem 0.4rem;border:none;border-radius:4px;cursor:pointer;font-size:0.65rem}
.btn-del{background:#dc2626;color:#fff}
.btn-view{background:#001943;color:#fff;margin-right:0.25rem}
.btn-print{background:#7c3aed;color:#fff}
.alert{padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem}
.alert-success{background:#dcfce7;color:#166534}
.alert-error{background:#fee2e2;color:#991b1b}
.modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:1rem}
.modal.active{display:flex}
.modal-content{background:#fff;max-width:500px;width:100%;max-height:90vh;overflow-y:auto;border-radius:12px;box-shadow:0 25px 50px rgba(0,0,0,0.3)}
.modal-header{background:#001943;color:#fff;padding:1rem;display:flex;justify-content:space-between;align-items:center}
.modal-header h3{margin:0;font-size:1rem}
.modal-close{background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer}
.modal-body{padding:1.5rem}
.receipt-company{text-align:center;margin-bottom:0.5rem}
.receipt-company h2{color:#001943;margin:0 0 0.25rem 0;font-weight:800;letter-spacing:0.5px;text-transform:uppercase}
.receipt-company p{color:#64748b;font-size:0.8rem;margin:0}
.receipt-divider{border-top:1px solid #001943;margin:0.5rem 0}
.receipt-info{margin-bottom:0.5rem;font-size:0.85rem}
.receipt-info p{margin:0.25rem 0;display:flex;justify-content:space-between}
.receipt-items{margin:0.5rem 0}
.receipt-row{display:grid;grid-template-columns:1.6fr 0.8fr 0.5fr 0.9fr;gap:6px;align-items:baseline}
.receipt-row .item-price,.receipt-row .item-qty,.receipt-row .item-total{text-align:right}
.receipt-item-header{font-size:0.7rem;font-weight:700;text-transform:uppercase;color:#0f172a}
.receipt-item{padding:0.35rem 0;border-bottom:1px dashed #e2e8f0}
.receipt-item:last-child{border-bottom:none}
.receipt-item-discount{display:flex;justify-content:space-between;font-size:0.7rem;margin-top:0.2rem}
.receipt-item-discount .receipt-amount{color:#dc2626}
.receipt-amount{font-weight:800}
.receipt-totals{margin-top:0.5rem;font-size:0.85rem}
.receipt-totals p{display:flex;justify-content:space-between;margin:0.25rem 0}
.receipt-totals .grand{font-size:1rem;font-weight:700;color:#001943}
.receipt-footer{text-align:center;margin-top:0.5rem;font-size:0.75rem;color:#64748b}
.receipt-footer p{margin:0.25rem 0}
@media(max-width:768px){
table,table thead,table tbody,table th,table td,table tr{display:block}
table thead{display:none}
table tr{margin-bottom:1rem;border:1px solid #e2e8f0;border-radius:8px;padding:0.5rem}
table td{display:flex;justify-content:space-between;padding:0.5rem;border:none}
table td:before{content:attr(data-label);font-weight:600;color:#001943}
}
</style>
</head>
<body>
<main class="container">
<div class="header">
<h1><i class="fas fa-history"></i> Debtors History<?php if ($selected_debtor) : $dinfo = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$debtors_table} WHERE id = %d", $selected_debtor)); if ($dinfo) : ?> - <?php echo esc_html($dinfo->name); ?><?php endif; endif; ?></h1>
<a href="<?php echo home_url('/debtors-record/'); ?>" class="btn btn-white"><i class="fas fa-user-clock"></i> Current Debtors</a>
</div>

<form method="GET" class="glass filters">
<div class="filter-group">
<label for="debtor">Filter by Debtor:</label>
<select name="debtor" id="debtor" class="select">
<option value="">All Debtors</option>
<?php foreach ($debtors as $d) : ?>
<option value="<?php echo esc_attr($d->id); ?>" <?php selected($selected_debtor, $d->id); ?>><?php echo esc_html($d->name); ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
</form>

<?php if ($message) : ?>
<div class="alert alert-<?php echo $message_type; ?>"><?php echo esc_html($message); ?></div>
<?php endif; ?>

<div class="glass">
<?php if (empty($history)) : ?>
<div class="empty"><i class="fas fa-inbox"></i><h3>No Transaction History</h3><p>Debtor transactions will appear here.</p></div>
<?php else : ?>
<div style="overflow-x:auto">
<table>
<thead><tr><th>Date</th><th>Time</th><th>Debtor</th><th>Type</th><th>Amount</th><th>Before</th><th>After</th><th>Details</th><th>Staff</th><?php if ($is_super_admin) : ?><th>Action</th><?php endif; ?></tr></thead>
<tbody>
<?php foreach ($history as $rec) : 
$type = $rec->transaction_type;
$badge = 'badge-' . $type;
$icon = $type === 'order' ? 'cart-plus' : ($type === 'payment' ? 'money-check' : ($type === 'initial' ? 'plus-circle' : 'edit'));
?>
<tr>
<td data-label="Date"><?php echo esc_html($rec->transaction_date); ?></td>
<td data-label="Time"><?php echo esc_html(cfi_format_receipt_time($rec->transaction_date, $rec->transaction_time)); ?></td>
<td data-label="Debtor"><?php echo esc_html($rec->debtor_name ?: 'Unknown'); ?></td>
<td data-label="Type"><span class="badge <?php echo esc_attr($badge); ?>"><i class="fas fa-<?php echo esc_attr($icon); ?>"></i> <?php echo esc_html(ucfirst($type)); ?></span></td>
<td data-label="Amount" style="font-weight:600;color:<?php echo $type === 'order' ? '#dc2626' : '#16a34a'; ?>"><?php echo $type === 'order' ? '+' : '-'; ?>₦<?php echo number_format((float)$rec->amount, 2); ?></td>
<td data-label="Before">₦<?php echo number_format((float)$rec->balance_before, 2); ?></td>
<td data-label="After" style="font-weight:600">₦<?php echo number_format((float)$rec->balance_after, 2); ?></td>
<td data-label="Details">
<?php if ($type === 'order' && $rec->order_id) : ?>
<button type="button" class="action-btn btn-view" onclick="viewOrder(<?php echo esc_attr($rec->order_id); ?>)"><i class="fas fa-eye"></i></button>
<button type="button" class="action-btn btn-print" onclick="reprintOrder(<?php echo esc_attr($rec->order_id); ?>)"><i class="fas fa-print"></i></button>
<?php elseif ($type === 'payment') : ?>
<button type="button" class="action-btn btn-view" onclick="viewPayment(<?php echo esc_attr($rec->id); ?>)"><i class="fas fa-eye"></i></button>
<button type="button" class="action-btn btn-print" onclick="reprintPay(<?php echo esc_attr($rec->id); ?>)"><i class="fas fa-print"></i></button>
<?php else : ?>-<?php endif; ?>
</td>
<td data-label="Staff"><?php echo esc_html($rec->staff_name ?: '-'); ?></td>
<?php if ($is_super_admin) : ?>
<td><form method="POST" style="display:inline" onsubmit="return confirm('Delete this?')"><input type="hidden" name="trans_id" value="<?php echo esc_attr($rec->id); ?>"><?php wp_nonce_field('cfi_del_trans', 'cfi_del_nonce'); ?><button type="submit" name="cfi_delete_trans" class="action-btn btn-del"><i class="fas fa-trash"></i></button></form></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</main>

<div class="modal" id="order-modal">
<div class="modal-content">
<div class="modal-header"><h3><i class="fas fa-receipt"></i> Order Details</h3><button type="button" class="modal-close" onclick="closeModal()">&times;</button></div>
<div class="modal-body" id="order-body"><div style="text-align:center;padding:2rem"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#001943"></i><p>Loading...</p></div></div>
</div>

<div class="modal" id="payment-modal">
<div class="modal-content">
<div class="modal-header"><h3><i class="fas fa-receipt"></i> Payment Details</h3><button type="button" class="modal-close" onclick="closePaymentModal()">&times;</button></div>
<div class="modal-body" id="payment-body"><div style="text-align:center;padding:2rem"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#001943"></i><p>Loading...</p></div></div>
</div>
</div>
</div>

<?php
// Prepare payment data for JavaScript
$pay_data = array();
foreach ($history as $rec) {
    if ($rec->transaction_type === 'payment') {
        $pay_data[$rec->id] = array(
            'id' => $rec->id,
            'debtor_name' => $rec->debtor_name,
            'amount' => $rec->amount,
            'payment_method' => $rec->payment_method,
            'cash_amount' => $rec->cash_amount,
            'transfer_amount' => $rec->transfer_amount,
            'bank_name' => $rec->bank_name,
            'home_amount' => $rec->home_calculation_amount,
            'balance_before' => $rec->balance_before,
            'balance_after' => $rec->balance_after,
            'transaction_date' => $rec->transaction_date,
            'transaction_time' => cfi_format_receipt_time($rec->transaction_date, $rec->transaction_time),
            'staff_name' => $rec->staff_name
        );
    }
}
?>
<script>
var payData=<?php echo json_encode($pay_data); ?>;
var cfiAjaxNonce = '<?php echo wp_create_nonce('cfi_nonce'); ?>';
var cfiCompanyName = 'CHINEMEREM FOODS';
var cfiCompanyTagline = 'Inventory Management System';

function escapeHtml(value) {
    var safeValue = (value === undefined || value === null) ? '' : value;
    return String(safeValue).replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[match];
    });
}

function buildOrderReceiptHtml(o){
    var html='';
    html+='<div class="receipt-company"><h2>'+cfiCompanyName+'</h2><p>'+cfiCompanyTagline+'</p></div>';
    html+='<div class="receipt-divider"></div>';
    html+='<div class="receipt-info">';
    html+='<p><span>Order #:</span><strong>'+escapeHtml(o.order_number||'N/A')+'</strong></p>';
    html+='<p><span>Date:</span><span>'+escapeHtml(o.order_date||'N/A')+'</span></p>';
    html+='<p><span>Time:</span><span>'+escapeHtml(o.order_time||'N/A')+'</span></p>';
    if(o.customer_name){html+='<p><span>Customer:</span><span>'+escapeHtml(o.customer_name)+'</span></p>'}
    html+='<p><span>Staff:</span><span>'+escapeHtml(o.staff_name||'Unknown')+'</span></p>';
    html+='</div>';
    html+='<div class="receipt-divider"></div>';
    html+='<div class="receipt-items">';
    html+='<div class="receipt-row receipt-item-header"><span>Item</span><span class="item-price">Price</span><span class="item-qty">Qty</span><span class="item-total">Total</span></div>';
    if(o.items&&o.items.length>0){o.items.forEach(function(i){var discountDisplay=Number(i.discount)>0?'-₦'+formatReceiptNumber(i.discount):'-';html+='<div class="receipt-item"><div class="receipt-row receipt-item-row"><span>'+escapeHtml(i.product_name)+'</span><span class="item-price receipt-amount">₦'+formatReceiptNumber(i.price)+'</span><span class="item-qty receipt-amount">'+formatReceiptNumber(i.quantity)+'</span><span class="item-total receipt-amount">₦'+formatReceiptNumber(i.total)+'</span></div><div class="receipt-item-discount"><span>Discount:</span><span class="receipt-amount">'+discountDisplay+'</span></div></div>'})}
    html+='</div>';
    html+='<div class="receipt-divider"></div>';
    html+='<div class="receipt-totals">';
    html+='<p><span>Subtotal:</span><span class="receipt-amount">₦'+formatReceiptNumber(o.total_amount||0)+'</span></p>';
    html+='<p><span>Total Discount:</span><span class="receipt-amount">-₦'+formatReceiptNumber(o.discount_amount||0)+'</span></p>';
    html+='<p class="grand"><span>Grand Total:</span><span class="receipt-amount">₦'+formatReceiptNumber(o.grand_total||0)+'</span></p>';
    html+='<p><span>Payment:</span><span>'+(o.payment_method||'Cash')+'</span></p>';
    if(o.transfer_amount>0){html+='<p><span>Transfer:</span><span class="receipt-amount">₦'+formatReceiptNumber(o.transfer_amount)+'</span></p>'}
    if(o.cash_amount>0){html+='<p><span>Cash:</span><span class="receipt-amount">₦'+formatReceiptNumber(o.cash_amount)+'</span></p>'}
    html+='</div>';
    html+='<div class="receipt-divider"></div>';
    html+='<div class="receipt-footer"><p>Thank you for your patronage!</p><p>Powered by BendlessTech</p></div>';
    return html;
}

function buildPaymentReceiptHtml(p){
    var method=String(p.payment_method||'cash').replace(/_/g,' ');
    var transferAmount=parseFloat(p.transfer_amount)||0;
    var cashAmount=parseFloat(p.cash_amount)||0;
    var homeAmount=parseFloat(p.home_amount)||0;
    var transferLine=transferAmount>0?'<p><span>Transfer:</span><span class="receipt-amount">₦'+formatReceiptNumber(transferAmount)+'</span></p>':'';
    var cashLine=cashAmount>0?'<p><span>Cash:</span><span class="receipt-amount">₦'+formatReceiptNumber(cashAmount)+'</span></p>':'';
    var homeLine=homeAmount>0?'<p><span>Home Calculation:</span><span class="receipt-amount">₦'+formatReceiptNumber(homeAmount)+'</span></p>':'';
    var bankLine=p.bank_name&&transferAmount>0?'<p><span>Bank:</span><span>'+escapeHtml(p.bank_name)+'</span></p>':'';
    var html='';
    html+='<div class="receipt-company"><h2>'+cfiCompanyName+'</h2><p>'+cfiCompanyTagline+'</p></div>';
    html+='<div class="receipt-divider"></div>';
    html+='<div class="receipt-info">';
    html+='<p><span>Receipt #:</span><strong>PAY-'+escapeHtml(p.id)+'</strong></p>';
    html+='<p><span>Date:</span><span>'+escapeHtml(p.transaction_date)+'</span></p>';
    html+='<p><span>Time:</span><span>'+escapeHtml(p.transaction_time)+'</span></p>';
    html+='<p><span>Debtor:</span><span>'+escapeHtml(p.debtor_name||'')+'</span></p>';
    html+='<p><span>Staff:</span><span>'+escapeHtml(p.staff_name||'-')+'</span></p>';
    html+='</div>';
    html+='<div class="receipt-divider"></div>';
    html+='<div class="receipt-totals">';
    html+='<p><span>Balance Before:</span><span class="receipt-amount" style="color:#dc2626">₦'+formatReceiptNumber(p.balance_before||0)+'</span></p>';
    html+='<p class="grand"><span>Payment Amount:</span><span class="receipt-amount">₦'+formatReceiptNumber(p.amount||0)+'</span></p>';
    html+='<p><span>Method:</span><span>'+escapeHtml(method)+'</span></p>';
    html+=transferLine+cashLine+homeLine+bankLine;
    html+='</div>';
    html+='<div class="receipt-divider"></div>';
    html+='<div class="receipt-info"><p><span>New Balance:</span><span class="receipt-amount">₦'+formatReceiptNumber(p.balance_after||0)+'</span></p></div>';
    if (p.balance_after < 0) {
        html+='<div class="receipt-info"><p><span>Overpayment Credit:</span><span class="receipt-amount" style="color:#16a34a">₦'+formatReceiptNumber(Math.abs(p.balance_after))+'</span></p></div>';
    }
    html+='<div class="receipt-footer"><p>Payment received with thanks</p><p>Powered by BendlessTech</p></div>';
    return html;
}

function viewOrder(id){
var modal=document.getElementById('order-modal'),body=document.getElementById('order-body');
closePaymentModal();
modal.classList.add('active');
fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=cfi_get_order_details&order_id='+id)
.then(function(r){return r.json()})
.then(function(d){
if(d.success){body.innerHTML=buildOrderReceiptHtml(d.data)}else{body.innerHTML='<div style="text-align:center;padding:2rem;color:#991b1b">Failed to load</div>'}
}).catch(function(){body.innerHTML='<div style="text-align:center;padding:2rem;color:#991b1b">Error loading</div>'});
}

function closeModal(){document.getElementById('order-modal').classList.remove('active')}

function viewPayment(id){
    var modal=document.getElementById('payment-modal'),body=document.getElementById('payment-body');
    closeModal();
    modal.classList.add('active');
    body.innerHTML='<div style="text-align:center;padding:2rem"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#001943"></i><p>Loading...</p></div>';
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({
            action: 'get_debtor_payment_details',
            payment_id: id,
            nonce: cfiAjaxNonce
        })
    }).then(function(r){return r.json()}).then(function(d){
        if(d.success){
            body.innerHTML=buildPaymentReceiptHtml(d.data);
        }else{
            body.innerHTML='<div style="text-align:center;padding:2rem;color:#991b1b">'+escapeHtml(d.message||'Payment not found')+'</div>';
        }
    }).catch(function(){
        body.innerHTML='<div style="text-align:center;padding:2rem;color:#991b1b">Error loading payment details</div>';
    });
}

function closePaymentModal(){document.getElementById('payment-modal').classList.remove('active')}

// Close modal on outside click or Escape
document.getElementById('payment-modal').addEventListener('click',function(e){if(e.target===this)closePaymentModal()});
if(!document.body.dataset.cfiDebtorHistoryEscapeHandler){
    document.body.dataset.cfiDebtorHistoryEscapeHandler='true';
    document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeModal();closePaymentModal()}});
}

// Bluetooth thermal printer support
var bluetoothDevice = null;
var printerCharacteristic = null;

async function connectBluetoothPrinter() {
    try {
        bluetoothDevice = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb', '49535343-fe7d-4ae5-8fa9-9fafd205e455', 'e7810a71-73ae-499d-8c15-faa9aef0c3f2']
        });
        const server = await bluetoothDevice.gatt.connect();
        const serviceUUIDs = ['000018f0-0000-1000-8000-00805f9b34fb', '49535343-fe7d-4ae5-8fa9-9fafd205e455', 'e7810a71-73ae-499d-8c15-faa9aef0c3f2'];
        for (let uuid of serviceUUIDs) {
            try {
                const service = await server.getPrimaryService(uuid);
                const characteristics = await service.getCharacteristics();
                for (let char of characteristics) {
                    if (char.properties.write || char.properties.writeWithoutResponse) {
                        printerCharacteristic = char;
                        return true;
                    }
                }
            } catch (e) { continue; }
        }
        const services = await server.getPrimaryServices();
        for (let service of services) {
            const chars = await service.getCharacteristics();
            for (let char of chars) {
                if (char.properties.write || char.properties.writeWithoutResponse) {
                    printerCharacteristic = char;
                    return true;
                }
            }
        }
        throw new Error('No writable characteristic found');
    } catch (error) {
        console.error('Bluetooth connection failed:', error);
        return false;
    }
}

async function printToBluetoothPrinter(text) {
    if (!printerCharacteristic) {
        const connected = await connectBluetoothPrinter();
        if (!connected) return false;
    }
    try {
        const encoder = new TextEncoder();
        const ESC = 0x1B;
        const GS = 0x1D;
        let commands = new Uint8Array([ESC, 0x40]);
        await printerCharacteristic.writeValue(commands);
        const textData = encoder.encode(text);
        const chunkSize = 100;
        for (let i = 0; i < textData.length; i += chunkSize) {
            const chunk = textData.slice(i, i + chunkSize);
            await printerCharacteristic.writeValue(chunk);
            await new Promise(r => setTimeout(r, 50));
        }
        commands = new Uint8Array([0x0A, 0x0A, 0x0A, GS, 0x56, 0x00]);
        await printerCharacteristic.writeValue(commands);
        return true;
    } catch (error) {
        console.error('Print failed:', error);
        printerCharacteristic = null;
        return false;
    }
}

function formatReceiptNumber(value) {
    var num = Number(value);
    if (Number.isNaN(num)) {
        return '0';
    }
    return num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function generateOrderESCPOS(o) {
    var text = '';
    var ESC = '\x1b';
    var GS = '\x1d';
    var boldOn = ESC + 'E' + '\x01';
    var boldOff = ESC + 'E' + '\x00';
    var doubleOn = GS + '!' + '\x11';
    var doubleOff = GS + '!' + '\x00';
    // 48 characters per line for 80mm thermal printers.
    var lineWidth = 48;
    var doubleWidth = Math.floor(lineWidth / 2);
    var itemWidth = 20;
    var priceWidth = 8;
    var qtyWidth = 5;
    var totalWidth = 12;
    var line = '-'.repeat(lineWidth);

    function centerText(text, width) {
        var useWidth = width || lineWidth;
        var padding = Math.floor((useWidth - text.length) / 2);
        return ' '.repeat(Math.max(0, padding)) + text;
    }

    function leftRight(left, right) {
        var space = lineWidth - left.length - right.length;
        return left + ' '.repeat(Math.max(1, space)) + right;
    }

    function leftRightBold(left, right) {
        var space = lineWidth - left.length - right.length;
        return left + ' '.repeat(Math.max(1, space)) + boldOn + right + boldOff;
    }

    text += doubleOn + boldOn + centerText('CHINEMEREM FOODS', doubleWidth) + boldOff + doubleOff + '\n';
    text += centerText('Credit Order (Reprint)') + '\n';
    text += line + '\n';
    text += 'Order: ' + (o.order_number||'N/A') + '\n';
    text += 'Date: ' + (o.order_date||'N/A') + '\n';
    text += 'Time: ' + (o.order_time||'N/A') + '\n';
    text += 'Customer: ' + (o.customer_name||'N/A') + '\n';
    text += line + '\n';
    text += 'ITEM'.padEnd(itemWidth) + ' ' + 'PRICE'.padStart(priceWidth) + ' ' + 'QTY'.padStart(qtyWidth) + ' ' + 'TOTAL'.padStart(totalWidth) + '\n';
    text += line + '\n';
    if(o.items&&o.items.length>0){o.items.forEach(function(i){
        var name = (i.product_name || '').substring(0, itemWidth).padEnd(itemWidth);
        var price = String(formatReceiptNumber(i.price)).padStart(priceWidth);
        var qty = String(formatReceiptNumber(i.quantity)).padStart(qtyWidth);
        var amt = String(formatReceiptNumber(i.total)).padStart(totalWidth);
        var discount = i.discount > 0 ? '-N' + formatReceiptNumber(i.discount) : '-';
        text += name + ' ' + boldOn + price + boldOff + ' ' + boldOn + qty + boldOff + ' ' + boldOn + amt + boldOff + '\n';
        text += leftRightBold('Discount:', discount) + '\n\n';
    })}
    text += line + '\n';
    var totalDiscount=0;
    if(o.items&&o.items.length>0){o.items.forEach(function(i){totalDiscount+=Number(i.discount)||0})}
    text += leftRightBold('Total Discount:', '-N' + String(formatReceiptNumber(totalDiscount))) + '\n';
    text += leftRightBold('ORDER TOTAL:', 'N' + String(formatReceiptNumber(o.grand_total||0))) + '\n';
    text += line + '\n';
    text += centerText('Credit order - Payment pending') + '\n';
    text += centerText('Powered by BendlessTech') + '\n';
    text += '\n\n\n';
    return text;
}

function generatePaymentESCPOS(p) {
    var text = '';
    var ESC = '\x1b';
    var GS = '\x1d';
    var boldOn = ESC + 'E' + '\x01';
    var boldOff = ESC + 'E' + '\x00';
    var doubleOn = GS + '!' + '\x11';
    var doubleOff = GS + '!' + '\x00';
    // 48 characters per line for 80mm thermal printers.
    var lineWidth = 48;
    var doubleWidth = Math.floor(lineWidth / 2);
    var line = '-'.repeat(lineWidth);

    function centerText(text, width) {
        var useWidth = width || lineWidth;
        var padding = Math.floor((useWidth - text.length) / 2);
        return ' '.repeat(Math.max(0, padding)) + text;
    }

    function leftRight(left, right) {
        var space = lineWidth - left.length - right.length;
        return left + ' '.repeat(Math.max(1, space)) + right;
    }

    function leftRightBold(left, right) {
        var space = lineWidth - left.length - right.length;
        return left + ' '.repeat(Math.max(1, space)) + boldOn + right + boldOff;
    }

    text += doubleOn + boldOn + centerText('CHINEMEREM FOODS', doubleWidth) + boldOff + doubleOff + '\n';
    text += centerText('Debt Payment (Reprint)') + '\n';
    text += line + '\n';
    text += 'Receipt: PAY-' + p.id + '\n';
    text += 'Date: ' + p.transaction_date + '\n';
    text += 'Time: ' + p.transaction_time + '\n';
    text += 'Debtor: ' + p.debtor_name + '\n';
    text += 'Staff: ' + (p.staff_name||'-') + '\n';
    text += line + '\n';
    text += leftRightBold('Balance Before:', 'N' + formatReceiptNumber(p.balance_before)) + '\n';
    text += leftRightBold('PAYMENT:', 'N' + formatReceiptNumber(p.amount)) + '\n';
    if(p.transfer_amount>0) text += leftRightBold('Via Transfer:', 'N' + formatReceiptNumber(p.transfer_amount)) + '\n';
    if(p.cash_amount>0) text += leftRightBold('Via Cash:', 'N' + formatReceiptNumber(p.cash_amount)) + '\n';
    text += line + '\n';
    text += leftRightBold('NEW BALANCE:', 'N' + formatReceiptNumber(p.balance_after)) + '\n';
    if(p.balance_after < 0) text += leftRightBold('OVERPAYMENT:', 'N' + formatReceiptNumber(Math.abs(p.balance_after))) + '\n';
    text += line + '\n';
    text += centerText('Payment received with thanks!') + '\n';
    text += centerText('Powered by BendlessTech') + '\n';
    text += '\n\n\n';
    return text;
}

function reprintOrder(id){
fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=cfi_get_order_details&order_id='+id)
.then(function(r){return r.json()})
.then(function(d){if(d.success)printOrder(d.data);else alert('Failed to load')})
.catch(function(){alert('Error loading')});
}

async function printOrder(o){
// Try Bluetooth first
if ('bluetooth' in navigator) {
    var receiptText = generateOrderESCPOS(o);
    var printed = await printToBluetoothPrinter(receiptText);
    if (printed) {
        return;
    }
}
// Fallback to browser print
var w=window.open('','_blank','width=350,height=700');
var h='<!DOCTYPE html><html><head><title>Print Receipt</title>';
h+='<style>';
h+='@page{size:80mm auto;margin:0}';
h+='*{margin:0;padding:0;box-sizing:border-box}';
    h+='body{font-family:"Courier New",Courier,monospace;font-size:12px;width:80mm;max-width:80mm;margin:0;padding:0;line-height:1.4;color:#000}';
    h+='.receipt{width:80mm;padding:2mm;box-sizing:border-box}';
    h+='.header{text-align:center;margin-bottom:6px}';
    h+='.header h2{font-size:16px;font-weight:800;margin:0 0 4px;letter-spacing:0.5px;text-transform:uppercase}';
    h+='.header p{font-size:11px;margin:0}';
    h+='.divider{border-top:1px solid #000;margin:6px 0}';
    h+='.info p{display:flex;justify-content:space-between;margin:4px 0;font-size:12px}';
    h+='.item-row{display:grid;grid-template-columns:1.6fr 0.8fr 0.5fr 0.9fr;gap:6px;align-items:baseline;font-size:11px}';
    h+='.item-row .item-price,.item-row .item-qty,.item-row .item-total{text-align:right}';
    h+='.item-header{font-size:10px;font-weight:700;text-transform:uppercase}';
    h+='.item{padding:4px 0;border-bottom:1px dashed #999}';
    h+='.item:last-child{border-bottom:none}';
    h+='.item-discount{display:flex;justify-content:space-between;font-size:10px;margin-top:2px}';
    h+='.receipt-amount{font-weight:800}';
    h+='.total p{display:flex;justify-content:space-between;margin:4px 0;font-size:12px}';
    h+='.footer{text-align:center;margin-top:6px;font-size:10px}';
h+='.no-print{margin:15px 0;text-align:center}';
h+='.print-btn{background:#7c3aed;color:#fff;border:none;padding:12px 30px;font-size:14px;border-radius:5px;cursor:pointer}';
h+='@media print{.no-print{display:none !important}}';
    h+='</style></head><body><div class="receipt">';
    h+='<div class="header"><h2>CHINEMEREM FOODS</h2><p>Credit Order Receipt (Reprint)</p></div>';
    h+='<div class="divider"></div>';
    h+='<div class="info">';
    h+='<p><span>Order #:</span><span>'+(o.order_number||'N/A')+'</span></p>';
    h+='<p><span>Date:</span><span>'+(o.order_date||'N/A')+'</span></p>';
    h+='<p><span>Time:</span><span>'+(o.order_time||'N/A')+'</span></p>';
    h+='<p><span>Customer:</span><span style="font-weight:700">'+(o.customer_name||'N/A')+'</span></p>';
    h+='</div>';
    h+='<div class="divider"></div>';
    h+='<div class="items">';
    h+='<div class="item-row item-header"><span>Item</span><span class="item-price">Price</span><span class="item-qty">Qty</span><span class="item-total">Total</span></div>';
    if(o.items&&o.items.length>0){var totalDiscount=0;o.items.forEach(function(i){
        var discountDisplay = Number(i.discount) > 0 ? '-N'+formatReceiptNumber(i.discount) : '-';
        totalDiscount+=Number(i.discount)||0;
    h+='<div class="item"><div class="item-row"><span>'+i.product_name+'</span><span class="item-price receipt-amount">N'+formatReceiptNumber(i.price)+'</span><span class="item-qty receipt-amount">'+formatReceiptNumber(i.quantity)+'</span><span class="item-total receipt-amount">N'+formatReceiptNumber(i.total)+'</span></div>';
    h+='<div class="item-discount"><span>Discount:</span><span class="receipt-amount">'+discountDisplay+'</span></div></div>';
    })}
    h+='</div>';
    h+='<div class="divider"></div>';
    h+='<div class="total">';
     h+='<p><span>TOTAL DISCOUNT:</span><span class="receipt-amount">-N'+formatReceiptNumber(totalDiscount||0)+'</span></p>';
     h+='<p><span>ORDER TOTAL:</span><span class="receipt-amount">N'+formatReceiptNumber(o.grand_total||0)+'</span></p>';
    h+='</div>';
    h+='<div class="divider"></div>';
    h+='<div class="footer"><p>This is a credit order - Payment pending</p><p style="margin-top:5px">Powered by BendlessTech</p></div>';
h+='</div></body></html>';
w.document.write(h);w.document.close();
w.onload=function(){setTimeout(function(){w.print()},300)};
}

async function reprintPay(id){
var p=payData[id];if(!p){alert('Not found');return}
// Try Bluetooth first
if ('bluetooth' in navigator) {
    var receiptText = generatePaymentESCPOS(p);
    var printed = await printToBluetoothPrinter(receiptText);
    if (printed) {
        return;
    }
}
// Fallback to browser print
var w=window.open('','_blank','width=350,height=700');
var h='<!DOCTYPE html><html><head><title>Print Receipt</title>';
h+='<style>';
h+='@page{size:80mm auto;margin:0}';
h+='*{margin:0;padding:0;box-sizing:border-box}';
    h+='body{font-family:"Courier New",Courier,monospace;font-size:12px;width:80mm;max-width:80mm;margin:0;padding:0;line-height:1.4;color:#000}';
    h+='.receipt{width:80mm;padding:2mm;box-sizing:border-box}';
    h+='.header{text-align:center;margin-bottom:6px}';
    h+='.header h2{font-size:16px;font-weight:800;margin:0 0 4px;letter-spacing:0.5px;text-transform:uppercase}';
    h+='.header p{font-size:11px;margin:0}';
    h+='.divider{border-top:1px solid #000;margin:6px 0}';
    h+='.info p{display:flex;justify-content:space-between;margin:4px 0;font-size:12px}';
    h+='.payment p{display:flex;justify-content:space-between;margin:4px 0;font-size:12px}';
    h+='.payment .big{font-size:13px;font-weight:700;color:#008800}';
    h+='.receipt-amount{font-weight:800}';
    h+='.total p{display:flex;justify-content:space-between;margin:4px 0;font-size:12px}';
    h+='.footer{text-align:center;margin-top:6px;font-size:10px}';
h+='.no-print{margin:15px 0;text-align:center}';
h+='.print-btn{background:#16a34a;color:#fff;border:none;padding:12px 30px;font-size:14px;border-radius:5px;cursor:pointer}';
h+='@media print{.no-print{display:none !important}}';
 h+='</style></head><body><div class="receipt">';
    h+='<div class="header"><h2>CHINEMEREM FOODS</h2><p>Debt Payment Receipt (Reprint)</p></div>';
    h+='<div class="divider"></div>';
    h+='<div class="info">';
    h+='<p><span>Receipt #:</span><span>PAY-'+p.id+'</span></p>';
    h+='<p><span>Date:</span><span>'+p.transaction_date+'</span></p>';
    h+='<p><span>Time:</span><span>'+p.transaction_time+'</span></p>';
    h+='<p><span>Debtor:</span><span style="font-weight:700">'+p.debtor_name+'</span></p>';
    h+='<p><span>Staff:</span><span>'+(p.staff_name||'-')+'</span></p>';
    h+='</div>';
    h+='<div class="divider"></div>';
    h+='<div class="payment">';
     h+='<p><span>Balance Before:</span><span class="receipt-amount" style="color:#cc0000">N'+formatReceiptNumber(p.balance_before)+'</span></p>';
     h+='<p class="big"><span>PAYMENT AMOUNT:</span><span class="receipt-amount">N'+formatReceiptNumber(p.amount)+'</span></p>';
     if(p.transfer_amount>0)h+='<p><span>  - Via Transfer:</span><span class="receipt-amount">N'+formatReceiptNumber(p.transfer_amount)+'</span></p>';
     if(p.cash_amount>0)h+='<p><span>  - Via Cash:</span><span class="receipt-amount">N'+formatReceiptNumber(p.cash_amount)+'</span></p>';
    h+='</div>';
    h+='<div class="divider"></div>';
    h+='<div class="total">';
    var balColor=parseFloat(p.balance_after)>0?'#cc0000':'#008800';
     h+='<p style="color:'+balColor+'"><span>NEW BALANCE:</span><span class="receipt-amount">N'+formatReceiptNumber(p.balance_after)+'</span></p>';
     if(parseFloat(p.balance_after)<0){h+='<p style="color:#008800"><span>OVERPAYMENT:</span><span class="receipt-amount">N'+formatReceiptNumber(Math.abs(p.balance_after))+'</span></p>'}
    h+='</div>';
    h+='<div class="divider"></div>';
     h+='<div class="footer"><p>Payment received with thanks!</p><p style="margin-top:5px">Powered by BendlessTech</p></div>';
h+='</div></body></html>';
w.document.write(h);w.document.close();
w.onload=function(){setTimeout(function(){w.print()},300)};
}

// Close modal on outside click or Escape
document.getElementById('order-modal').addEventListener('click',function(e){if(e.target===this)closeModal()});

// Prevent form resubmission on back button - but do NOT auto-reload
if(window.history.replaceState)window.history.replaceState(null,null,window.location.href);
</script>
</body>
</html>
