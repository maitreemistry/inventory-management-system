<?php
ob_start();
$servername = "";
$username = "";
$password = "";
$dbname = "inventorydb";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$table = isset($_GET['table']) ? strtolower($_GET['table']) : 'suppliers';

$columns = [
    "suppliers" => ["supplier_id", "name", "contact_person", "phone", "email", "address"],
    "products" => ["product_id", "name", "category", "price", "quantity_in_stock", "supplier_id", "warehouse_id"],
    "warehouses" => ["warehouse_id", "location", "capacity"],
    "sales" => ["sale_id", "sale_date", "total_amount"],
    "salesdetails" => ["detail_id", "sale_id", "product_id", "quantity", "price"],
    "purchaseorders" => ["order_id", "supplier_id", "order_date", "status", "total_amount"],
    "purchaseorderdetails" => ["detail_id", "order_id", "product_id", "quantity", "price"]
];

$primaryKeys = [
    "suppliers" => "supplier_id",
    "products" => "product_id",
    "warehouses" => "warehouse_id",
    "sales" => "sale_id",
    "salesdetails" => "detail_id",
    "purchaseorders" => "order_id",
    "purchaseorderdetails" => "detail_id"
];

// Pagination setup
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;
$totalResult = $conn->query("SELECT COUNT(*) as total FROM $table");
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// DELETE
if (isset($_GET['delete_id'])) {
    $delete_id = $conn->real_escape_string($_GET['delete_id']);
    $pk = $primaryKeys[$table];

    if ($table === 'salesdetails') {
        $detail = $conn->query("SELECT product_id, quantity FROM salesdetails WHERE detail_id = '$delete_id'")->fetch_assoc();
        $conn->query("UPDATE products SET quantity_in_stock = quantity_in_stock + {$detail['quantity']} WHERE product_id = '{$detail['product_id']}'");
    }
    if ($table === 'purchaseorderdetails') {
        $detail = $conn->query("SELECT product_id, quantity FROM purchaseorderdetails WHERE detail_id = '$delete_id'")->fetch_assoc();
        $conn->query("UPDATE products SET quantity_in_stock = quantity_in_stock - {$detail['quantity']} WHERE product_id = '{$detail['product_id']}'");
    }

    $conn->query("DELETE FROM $table WHERE $pk = '$delete_id'");
    header("Location: inventory.php?table=$table&page=$page");
    exit();
}

// EDIT
$editMode = false;
$editData = [];
if (isset($_GET['edit_id'])) {
    $editMode = true;
    $edit_id = $conn->real_escape_string($_GET['edit_id']);
    $pk = $primaryKeys[$table];
    $result = $conn->query("SELECT * FROM $table WHERE $pk = '$edit_id'");
    $editData = $result->fetch_assoc();
}

// ADD or UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_entry'])) {
    $pk = $primaryKeys[$table];
    $colNames = array_slice($columns[$table], 1);
    $values = [];
    foreach ($colNames as $col) {
        $values[$col] = $conn->real_escape_string($_POST[$col]);
    }

    if ($table == 'salesdetails' || $table == 'purchaseorderdetails') {
        $product_id = $values['product_id'];
        $quantity = (int)$values['quantity'];

        if ($editMode) {
            $oldDetail = $conn->query("SELECT quantity FROM $table WHERE $pk = '{$_POST['id']}'")->fetch_assoc();
            $oldQuantity = (int)$oldDetail['quantity'];
            $diff = $quantity - $oldQuantity;

            if ($table === 'salesdetails') {
                $stock = $conn->query("SELECT quantity_in_stock FROM products WHERE product_id = '$product_id'")->fetch_assoc()['quantity_in_stock'];
                if ($stock < $diff) {
                    echo "<script>alert('Not enough stock for update');</script>";
                } else {
                    $setParts = [];
                    foreach ($colNames as $col) $setParts[] = "$col = '{$values[$col]}'";
                    $conn->query("UPDATE $table SET " . implode(", ", $setParts) . " WHERE $pk = '{$_POST['id']}'");
                    $conn->query("UPDATE products SET quantity_in_stock = quantity_in_stock - $diff WHERE product_id = '$product_id'");
                    header("Location: inventory.php?table=$table&page=$page");
                    exit();
                }
            } else {
                $setParts = [];
                foreach ($colNames as $col) $setParts[] = "$col = '{$values[$col]}'";
                $conn->query("UPDATE $table SET " . implode(", ", $setParts) . " WHERE $pk = '{$_POST['id']}'");
                $conn->query("UPDATE products SET quantity_in_stock = quantity_in_stock + $diff WHERE product_id = '$product_id'");
                header("Location: inventory.php?table=$table&page=$page");
                exit();
            }
        } else {
            if ($table === 'salesdetails') {
                $stock = $conn->query("SELECT quantity_in_stock FROM products WHERE product_id = '$product_id'")->fetch_assoc()['quantity_in_stock'];
                if ($stock < $quantity) {
                    echo "<script>alert('Not enough stock');</script>";
                } else {
                    $cols = implode(", ", array_keys($values));
                    $vals = "'" . implode("','", array_values($values)) . "'";
                    $conn->query("INSERT INTO $table ($cols) VALUES ($vals)");
                    $conn->query("UPDATE products SET quantity_in_stock = quantity_in_stock - $quantity WHERE product_id = '$product_id'");
                    header("Location: inventory.php?table=$table&page=$page");
                    exit();
                }
            } else {
                $cols = implode(", ", array_keys($values));
                $vals = "'" . implode("','", array_values($values)) . "'";
                $conn->query("INSERT INTO $table ($cols) VALUES ($vals)");
                $conn->query("UPDATE products SET quantity_in_stock = quantity_in_stock + $quantity WHERE product_id = '$product_id'");
                header("Location: inventory.php?table=$table&page=$page");
                exit();
            }
        }
    } else {
        if ($editMode) {
            $setParts = [];
            foreach ($colNames as $col) $setParts[] = "$col = '{$values[$col]}'";
            $conn->query("UPDATE $table SET " . implode(", ", $setParts) . " WHERE $pk = '{$_POST['id']}'");
        } else {
            $cols = implode(", ", array_keys($values));
            $vals = "'" . implode("','", array_values($values)) . "'";
            $conn->query("INSERT INTO $table ($cols) VALUES ($vals)");
        }
        header("Location: inventory.php?table=$table&page=$page");
        exit();
    }
}

// Fetching dropdown data
$result = $conn->query("SELECT * FROM $table LIMIT $limit OFFSET $offset");
$products = $conn->query("SELECT product_id, name FROM products");
$suppliers = $conn->query("SELECT supplier_id, name FROM suppliers");
$warehouses = $conn->query("SELECT warehouse_id, location FROM warehouses");
$sales = $conn->query("SELECT sale_id FROM sales");
$orders = $conn->query("SELECT order_id FROM purchaseorders");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Management</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f4f9; margin: 0; padding: 0; }
        header { background-color: #8e6e95; color: white; padding: 20px; text-align: center; }
        .mahek { background-color: #8e6e95; color: white; }
        table { width: 90%; margin: auto; border-collapse: collapse; background-color: #fff; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #d3b8d8; color: #333; }
        tr:nth-child(even) { background-color: #f6edf9; }
        tr:hover { background-color: #e9dff2; }
        .form-wrapper { display: flex; justify-content: center; margin: 30px 0; }
        .form-container { display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; background-color: white; padding: 20px; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 0 10px rgba(142, 110, 149, 0.1); }
        .form-container label { display: block; font-size: 14px; margin-bottom: 3px; color: #555; }
        .form-container input, .form-container select { padding: 8px; font-size: 14px; width: 150px; border: 1px solid #ccc; border-radius: 5px; }
        .form-container button { width: 150px; background-color: #b07bac; color: white; padding: 12px; border: none; cursor: pointer; margin-top: 10px; border-radius: 5px; transition: background-color 0.3s; }
        .form-container button:hover { background-color: #9b5f94; }
        .table-selector { text-align: center; margin: 20px; }
    </style>
</head>
<body>
<div class="mahek">
<a href="index.html" style="color: white; font-size: 1.2rem; text-decoration: none;">Home</a>&nbsp&nbsp&nbsp&nbsp<a href="graph.php" style="color: white; font-size: 1.2rem; text-decoration: none;">Graph</a>&nbsp&nbsp&nbsp&nbsp<a href="er.html" style="color: white; font-size: 1.2rem; text-decoration: none; text-align:center;">ER Diagram</a>
</div>
<header>
    <!-- <a href="graph.php" style="color: white; font-size: 1.2rem; text-decoration: none;">Graph</a> -->
    <h1>INVENTORY MANAGEMENT SYSTEM</h1>
</header>

<div class="table-selector">
    <form method="GET" action="inventory.php">
        <label>Select Table: </label>
        <select name="table" onchange="this.form.submit()">
            <?php foreach ($columns as $tbl => $cols) {
                $selected = ($table == $tbl) ? "selected" : "";
                echo "<option value='$tbl' $selected>" . ucfirst($tbl) . "</option>";
            } ?>
        </select>
    </form>
</div>

<h2 style="text-align:center;">Manage <?php echo ucfirst($table); ?></h2>

<table>
    <?php
    if ($result->num_rows > 0) {
        echo "<tr>";
        foreach ($columns[$table] as $col) {
            echo "<th>" . ucfirst(str_replace("_", " ", $col)) . "</th>";
        }
        echo "<th>Actions</th></tr>";

        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($columns[$table] as $col) {
                echo "<td>{$row[$col]}</td>";
            }
            $pkVal = $row[$primaryKeys[$table]];
            echo "<td><a href='?table=$table&edit_id=$pkVal&page=$page' style='color: #b07bac;'>Edit</a> | <a href='?table=$table&delete_id=$pkVal&page=$page' style='color: #b07bac;' onclick='return confirm(\"Are you sure?\")'>Delete</a></td></tr>";
        }
    } else {
        echo "<tr><td colspan='100%'>No records found</td></tr>";
    }
    ?>
</table>

<div style="text-align:center; margin: 20px;">
    <?php if ($page > 1): ?>
        <a href="?table=<?php echo $table; ?>&page=<?php echo $page - 1; ?>" style="margin-right: 15px; color: #b07bac;">&laquo; Previous</a>
    <?php endif; ?>
    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
    <?php if ($page < $totalPages): ?>
        <a href="?table=<?php echo $table; ?>&page=<?php echo $page + 1; ?>" style="margin-left: 15px; color: #b07bac; ">Next &raquo;</a>
    <?php endif; ?>
</div>

<div class="form-wrapper">
    <form method="post">
        <div class="form-container">
            <?php
            if ($editMode) echo "<input type='hidden' name='id' value='{$editData[$primaryKeys[$table]]}'>";
            foreach (array_slice($columns[$table], 1) as $col) {
                $val = $editMode ? htmlspecialchars($editData[$col]) : "";
                echo "<div><label for='$col'>" . ucfirst(str_replace("_", " ", $col)) . "</label>";

                if ($col == 'supplier_id') {
                    echo "<select name='$col'>";
                    mysqli_data_seek($suppliers, 0);
                    while ($s = $suppliers->fetch_assoc()) {
                        $selected = ($s['supplier_id'] == $val) ? 'selected' : '';
                        echo "<option value='{$s['supplier_id']}' $selected>{$s['name']}</option>";
                    }
                    echo "</select>";
                } elseif ($col == 'warehouse_id') {
                    echo "<select name='$col'>";
                    mysqli_data_seek($warehouses, 0);
                    while ($w = $warehouses->fetch_assoc()) {
                        $selected = ($w['warehouse_id'] == $val) ? 'selected' : '';
                        echo "<option value='{$w['warehouse_id']}' $selected>{$w['location']}</option>";
                    }
                    echo "</select>";
                } elseif ($col == 'product_id') {
                    echo "<select name='$col'>";
                    mysqli_data_seek($products, 0);
                    while ($p = $products->fetch_assoc()) {
                        $selected = ($p['product_id'] == $val) ? 'selected' : '';
                        echo "<option value='{$p['product_id']}' $selected>{$p['name']}</option>";
                    }
                    echo "</select>";
                } elseif ($col == 'sale_id') {
                    echo "<select name='$col'>";
                    mysqli_data_seek($sales, 0);
                    while ($s = $sales->fetch_assoc()) {
                        $selected = ($s['sale_id'] == $val) ? 'selected' : '';
                        echo "<option value='{$s['sale_id']}' $selected>{$s['sale_id']}</option>";
                    }
                    echo "</select>";
                } elseif ($col == 'order_id') {
                    echo "<select name='$col'>";
                    mysqli_data_seek($orders, 0);
                    while ($o = $orders->fetch_assoc()) {
                        $selected = ($o['order_id'] == $val) ? 'selected' : '';
                        echo "<option value='{$o['order_id']}' $selected>{$o['order_id']}</option>";
                    }
                    echo "</select>";
                } else {
                    echo "<input type='text' name='$col' value='$val' required>";
                }
                echo "</div>";
            }
            ?>
            <button type="submit" name="save_entry">Save Entry</button>
        </div>
    </form>
</div>
</body>
</html>

<?php $conn->close(); ?>
