<?php
$servername = "";
$username = "";
$password = "";
$dbname = "inventorydb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$data = [];
$sql = "SELECT name, quantity_in_stock FROM products";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = ["country" => $row["name"], "value" => (int)$row["quantity_in_stock"]];
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Stock Chart</title>
    <style>
        #chartdiv {
            width: 100%;
            height: 500px;
        }
        body { 
            font-family: Arial, sans-serif; 
            background-color:#f8f4f9; 
            margin: 0;
            padding: 0;
        }
    </style>
    <!-- Resources -->
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
</head>
<body>

<a href="index.html" style="color: #9b5f94; font-size: 1.2rem; text-decoration: none; text-align:center;">Home</a>&nbsp&nbsp&nbsp&nbsp<a href="inventory.php" style="color: #9b5f94; font-size: 1.2rem; text-decoration: none; text-align:center;">Database</a>&nbsp&nbsp&nbsp&nbsp<a href="er.html" style="color: #9b5f94; font-size: 1.2rem; text-decoration: none; text-align:center;">ER Diagram</a>
<!-- <a href="index.html" style="color: #9b5f94; font-size: 1.2rem; text-decoration: none; text-align:center;">Home</a> -->
<h2 style="text-align:center; color: #9b5f94;">Dashboard</h2>
<div id="chartdiv"></div>

<script>
am5.ready(function() {
    var root = am5.Root.new("chartdiv");

    root.setThemes([
        am5themes_Animated.new(root)
    ]);

    var chart = root.container.children.push(am5xy.XYChart.new(root, {
        panX: true,
        panY: true,
        wheelX: "panX",
        wheelY: "zoomX",
        pinchZoomX: true,
        paddingLeft: 0,
        paddingRight: 1
    }));

    var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {}));
    cursor.lineY.set("visible", false);

    var xRenderer = am5xy.AxisRendererX.new(root, {
        minGridDistance: 30,
        minorGridEnabled: true
    });

    xRenderer.labels.template.setAll({
        rotation: -90,
        centerY: am5.p50,
        centerX: am5.p100,
        paddingRight: 15
    });

    var xAxis = chart.xAxes.push(am5xy.CategoryAxis.new(root, {
        maxDeviation: 0.3,
        categoryField: "country",
        renderer: xRenderer,
        tooltip: am5.Tooltip.new(root, {})
    }));

    var yRenderer = am5xy.AxisRendererY.new(root, {
        strokeOpacity: 0.1
    });

    var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
        maxDeviation: 0.3,
        renderer: yRenderer
    }));

    var series = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: "Stock Quantity",
        xAxis: xAxis,
        yAxis: yAxis,
        valueYField: "value",
        sequencedInterpolation: true,
        categoryXField: "country",
        tooltip: am5.Tooltip.new(root, {
            labelText: "{valueY}"
        })
    }));

    series.columns.template.setAll({ cornerRadiusTL: 5, cornerRadiusTR: 5, strokeOpacity: 0 });
    series.columns.template.adapters.add("fill", function(fill, target) {
        return chart.get("colors").getIndex(series.columns.indexOf(target));
    });

    series.columns.template.adapters.add("stroke", function(stroke, target) {
        return chart.get("colors").getIndex(series.columns.indexOf(target));
    });

    // PHP to JS Data Injection
    var data = <?php echo json_encode($data); ?>;

    xAxis.data.setAll(data);
    series.data.setAll(data);

    series.appear(1000);
    chart.appear(1000, 100);
});
</script>

</body>
</html>
