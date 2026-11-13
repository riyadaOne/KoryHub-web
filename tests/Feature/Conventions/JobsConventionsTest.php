<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

/*
 * Les jobs ne transportent que des identifiants scalaires, jamais un model (TRD §19, T-06, piège 6).
 */
test('aucun job ne reçoit un model dans son constructeur', function () {
    $folder = app_path('Jobs');
    $jobs = File::isDirectory($folder) ? File::allFiles($folder) : [];

    foreach ($jobs as $file) {
        $class = 'App\\Jobs\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists($class) || ! is_subclass_of($class, ShouldQueue::class)) {
            continue;
        }

        foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            expect($typeName !== null && is_a($typeName, Model::class, true))
                ->toBeFalse("{$class} reçoit un model ({$parameter->getName()}) : passer son identifiant.");
        }
    }

    expect($jobs)->toBeIterable();
});
