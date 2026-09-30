<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class GarmentCondition
 * 
 * @property int $id
 * @property int $garment_id
 * @property string $condition_type
 * @property string|null $description
 * @property string|null $photo
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Garment $garment
 *
 * @package App\Models
 */
class GarmentCondition extends Model
{
	use SoftDeletes;
	protected $table = 'garment_conditions';
	public static $snakeAttributes = false;

	protected $casts = [
		'garment_id' => 'int'
	];

	protected $fillable = [
		'garment_id',
		'condition_type',
		'description',
		'photo',
		'status'
	];

	public function garment()
	{
		return $this->belongsTo(Garment::class);
	}
}
