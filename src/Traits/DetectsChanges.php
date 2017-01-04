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

        if ([] !== static::$logAttributes) {
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

        if (isset(static::$logAttributes) && [] === static::$logAttributes) {
            $changes->mapWithKeys(function ($item, $key) use ($model) {
                if (array_key_exists($key, static::$logAttributes[$key])) {
                    if (is_array(static::$logAttributes[$key])) {
                        $property = static::$logAttributes[$key];
                        $relation = $property['relation'];
                        $key = $property['key'];
                        $field = $property['field'];

                        if ($model->$relation) {
                            return [
                                $key => [
                                    'id' => $model->$relation->id,
                                    'value' => $model->$relation->$field,
                                ],
                            ];
                        }

                        return;
                    } else {
                        return [static::$logAttributes[$key] => $item];
                    }
                }

                return [$key => $item];
            });
        }

        return $changes->toArray();
    }
}
