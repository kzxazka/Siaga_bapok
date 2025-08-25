<?php
echo "Syntax test started...<br>";

// Test basic syntax
$test = "Hello World";
echo $test . "<br>";

// Test array
$array = [1, 2, 3];
echo "Array count: " . count($array) . "<br>";

// Test function
function testFunction() {
    return "Function works";
}
echo testFunction() . "<br>";

// Test try-catch
try {
    echo "Try block works<br>";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}

echo "Syntax test completed successfully!<br>";
?>