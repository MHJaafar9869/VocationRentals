<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;
    protected $table = "properties";
    protected $fillable = [
        "name",
        "headline",
        "description",
        "number_of_rooms",
        "city",
        "country",
        "address",
        "night_rate",
        "status",
        "category_id",
        'latitude',
        'longitude',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, "category_id", "id");
    }

    public function owner()
    {
        return $this->belongsTo(User::class, "owner_id", "id");
    }

    public function booking()
    {
        return $this->hasMany(Booking::class);
    }
    public function propertyImages()
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function propertyAmenities()
    {
        return $this->hasMany(PropertyAmenity::class);
    }
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

}