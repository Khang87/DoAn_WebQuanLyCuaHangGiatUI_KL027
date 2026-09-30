<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ServiceCategory
 * 
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|Service[] $services
 *
 * @package App\Models
 */
class ServiceCategory extends Model
{
	use SoftDeletes;
	protected $table = 'service_categories';
	public static $snakeAttributes = false;

	protected $fillable = [
		'name',
		'slug',
		'description',
		'icon',
		'status'
	];

	public function services()
	{
		return $this->hasMany(Service::class);
	}
}
