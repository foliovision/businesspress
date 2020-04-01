<?php

/*
Taken from: https://wordpress.stackexchange.com/questions/2331/sort-admin-menu-items
*/

add_action( 'admin_menu', 'sort_settings_menu_wpse_2331', 999 );

function sort_settings_menu_wpse_2331() 
{
    global $submenu;

    // Sort default items
    $default = array_slice( $submenu['options-general.php'], 0, 6, true );
    
    // Leave default items unsorted
    //usort( $default, 'sort_arra_asc_so_1597736' );

    // Sort rest of items
    $length = count( $submenu['options-general.php'] );
    $extra = array_slice( $submenu['options-general.php'], 6, $length, true );
    usort( $extra, 'sort_arra_asc_so_1597736' );

    // Apply
    $submenu['options-general.php'] = array_merge( $default, $extra );
}

//http://stackoverflow.com/a/1597788/1287812
function sort_arra_asc_so_1597736( $item1, $item2 )
{
    if ($item1[0] == $item2[0]) return 0;
    return ( $item1[0] > $item2[0] ) ? 1 : -1;
}