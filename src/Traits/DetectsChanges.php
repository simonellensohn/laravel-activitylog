<?php

namespace Spatie\Activitylog\Traits;

use Illuminate\Database\Eloquent\Model;

trait DetectsChanges
{
    protected $oldAttributes = [];

    protected static function bootDetectsChanges()
    {
        if (static::eventsToBeRecorded()->contains('updated')) {
            static::updating(function (Model $model) {

                //temporary hold the original attributes on the model
                //as we'll need these in the updating event
                $oldValues = $model->replicate()->setRawAttributes($model->getOriginal());

                $model->oldAttributes = static::logChanges($oldValues);
            });
        }
    }

    public function attributesToBeLogged(): array
    {
        if (! isset(static::$logAttributes)) {
            return [];
        }

        if (self::isAssoc(static::$logAttributes)) {
            return collect(static::$logAttributes)->keys()->all();
        }

        return static::$logAttributes;
    }

    public function attributeValuesToBeLogged(string $processingEvent): array
    {
        if (! count($this->attributesToBeLogged())) {
            return [];
        }

        $properties['attributes'] = static::logChanges($this);

        if (static::eventsToBeRecorded()->contains('updated') && $processingEvent == 'updated') {
            $nullProperties = array_fill_keys(array_keys($properties['attributes']), null);

            $properties['old'] = array_merge($nullProperties, $this->oldAttributes);
        }

        return $properties;
    }

    public static function logChanges(Model $model): array
    {
        $changes = collect($model)->only($model->attributesToBeLogged());
        $attributes = isset(static::$logAttributes) ? static::$logAttributes : null;

        if ($attributes && self::isAssoc($attributes)) {
            $changes = $changes->mapWithKeys(function ($item, $key) use ($model, $attributes) {
                if (array_key_exists($key, $attributes)) {
                    if (is_array($attributes[$key])) {
                        $property = $attributes[$key];
                        $relation = $property['relation'];
                        $key = $property['key'];
                        $field = $property['field'];

                        if ($model->$relation) {
                            return [
                                $key => [
                                    'id' => $model->$relation->id,
                                    'value' => $model->$relation->$field
                                ]
                            ];
                        }

                        return;
                    } else {
                        return [$attributes[$key] => $item];
                    }
                }

                return [$key => $item];
            });
        }

        return $changes->toArray();
    }

    private static function isAssoc(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
