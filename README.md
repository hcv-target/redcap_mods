# redcap_mods
Modifications to HCV-TARGET REDCap

Sometimes, we need to add functionality to areas of REDCap that haven't been exposed using hooks or API. Here, we wanted to add information to the Data Entry Grid without resorting to hacking REDCap's core. This is implemented using apache configuration, redirecting any calls for redcap_dir/DataEntry/grid.php to redcap_dir/Plugins/advanced_grid.php.

As with all of the HCV-TARGET repos, no warranty either express or implied is made for the suitability of any of this code to a given purpose. I cannot guarantee it will work for you. I can also not guarantee that if it works today, it won't break tomorrow. Such is the nature of HCV-TARGET. So here is us, on the raggedy edge. Furthermore, some of it is derivative of others' work. Where this is the case, attributions have been made in the source code.
