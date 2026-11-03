<?php
// ================= LOAD ARBITRATION CONFIG =================

$arb_config = [];
$arb_config_result = mysqli_query($conn, "SELECT config_key, config_value FROM tbl_arbitration_config");
if ($arb_config_result) {
    while ($row = mysqli_fetch_assoc($arb_config_result)) {
        $arb_config[$row['config_key']] = $row['config_value'];
    }
}
