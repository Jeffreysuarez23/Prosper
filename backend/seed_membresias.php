<?php
use App\Models\User;
use App\Models\Membresia;

User::doesntHave('membresia')->get()->each(function($u) { 
    Membresia::create(['user_id' => $u->id, 'plan' => 'gratis', 'status' => 'active']); 
});
echo "Done.";
