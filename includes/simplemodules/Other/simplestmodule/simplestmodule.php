<?php

hbm_create('Simplest module');  //at this point HostBill recognizes this module

// Create default page for this module
hbm_client_route('', function($request) {

    $url_to_subpage = hbm_client_url('subpage');

    echo '<div class="hero-unit">
        <h1>Hello World!</h1>
        <p>This is simple plugin for HostBill, built using procedural devkit</p>
        <p><a class="btn btn-primary btn-large" href="' . $url_to_subpage . '">See different page</a></p>
        </div>';

    //effect: http://screencast.com/t/TrotHDeY
});

// Create another page (route)
hbm_client_route('subpage', function($request) {
             echo '<div class="hero-unit">
        <h1>This is simple subpage</h1>
        <p>This is simple plugin for HostBill, built using procedural devkit</p>
        </div>';
 });


 
//add link to clientarea main menu interface:
hbm_register_module_link('client.mainmenu');
    