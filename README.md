# redcap_mods
Modifications to HCV-TARGET REDCap

Sometimes, we need to add functionality to areas of REDCap that haven't been exposed using hooks or API. Here, we wanted to add information to the Data Entry Grid without resorting to hacking REDCap's core. This is implemented using apache configuration, redirecting any calls for redcap_dir/DataEntry/grid.php to redcap_dir/Plugins/advanced_grid.php.
