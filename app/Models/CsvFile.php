<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CsvFile extends Model
{
    use HasFactory;

    // Mass-assignable fields
    protected $fillable = ['original_name', 'stored_path', 'user_id'];
}