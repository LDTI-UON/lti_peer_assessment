<?php
if(isset($_COOKIE["filter_submitted"]) && !empty($_COOKIE["filter_submitted"])) {
    $plugin_filters["filter_submitted"] = $_COOKIE["filter_submitted"];
}
echo(empty($_POST['filter_submitted']));
if(empty($_POST['filter_submitted'])) {
    unset($_COOKIE["filter_submitted"]);
}
if(isset($_POST['filter_submitted']) && !empty($_POST['filter_submitted'])) {
    $plugin_filters["filter_submitted"] = $_POST['filter_submitted'];

    unset($_COOKIE["filter_submitted"]);
    setcookie("filter_submitted", $plugin_filters["filter_submitted"], time() + 1800, $this->base_url);
}

?>
