<?php
namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $guarded = ['id'];

    // الخاصية تملك عدة قيم (اللون يملك: أحمر، أخضر، أزرق)
    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    // المنتجات التي تستخدم هذه الخاصية
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attributes');
    }
}