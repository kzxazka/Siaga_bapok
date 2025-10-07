function responsive_table($data, $columns) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover">';
    echo '<thead><tr>';
    foreach ($columns as $column) {
        echo '<th>'.$column.'</th>';
    }
    echo '</tr></thead><tbody>';
    
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($columns as $key => $label) {
            echo '<td data-label="'.$label.'">'.htmlspecialchars($row[$key]).'</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
}