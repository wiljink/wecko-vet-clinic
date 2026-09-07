<?php

namespace App\Support;

use Faker\Provider\Base;

/**
 * Filipino names for people and pets.
 *
 * The bundled Faker `en_PH` locale only ships Address + PhoneNumber data, so
 * person names still fall back to US data. This provider is prepended to the
 * generator (see AppServiceProvider) so `name()`, `firstName()`, `lastName()`
 * and `petName()` return Philippine-appropriate values in seeders and factories.
 */
class FilipinoFaker extends Base
{
    protected static array $firstNameMale = [
        'Juan', 'Jose', 'Miguel', 'Angelo', 'Mark', 'John Paul', 'Carlo', 'Paolo',
        'Rafael', 'Emmanuel', 'Jerome', 'Christian', 'Kevin', 'Renato', 'Ronaldo',
        'Ferdinand', 'Rodrigo', 'Benjamin', 'Noel', 'Efren', 'Ramon', 'Danilo',
        'Roberto', 'Antonio', 'Eduardo', 'Rey', 'Arnel', 'Jayson', 'Michael', 'Dennis',
    ];

    protected static array $firstNameFemale = [
        'Maria', 'Rosa', 'Josefina', 'Cristina', 'Angelica', 'Jasmine', 'Grace',
        'Divine', 'Kim', 'Andrea', 'Beatriz', 'Nora', 'Vilma', 'Sharon', 'Corazon',
        'Imelda', 'Liza', 'Angeline', 'Camille', 'Aileen', 'Mary Grace', 'Ana',
        'Lorna', 'Marilou', 'Jocelyn', 'Precious', 'Trisha', 'Nicole', 'Danica', 'Rowena',
    ];

    protected static array $lastName = [
        'Dela Cruz', 'Santos', 'Reyes', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza',
        'Torres', 'Castillo', 'Villanueva', 'Ramos', 'Aquino', 'Gonzales', 'Fernandez',
        'Del Rosario', 'Salazar', 'Mercado', 'Aguilar', 'Domingo', 'Navarro', 'Flores',
        'Rivera', 'Cruz', 'Bernardo', 'Pascual', 'Manalo', 'Pangilinan', 'Lim', 'Tan',
        'Sy', 'Uy', 'Co', 'Chua', 'Gutierrez', 'Diaz', 'Rosales', 'Marasigan', 'Alonzo',
    ];

    protected static array $petName = [
        'Bantay', 'Brownie', 'Blackie', 'Bruno', 'Max', 'Rocky', 'Chico', 'Muning',
        'Ming-Ming', 'Puti', 'Bogart', 'Tisoy', 'Snowy', 'Buboy', 'Chikoy', 'Kitkat',
        'Coco', 'Milo', 'Peanut', 'Ginger', 'Shadow', 'Simba', 'Luna', 'Bella',
        'Princess', 'Happy', 'Lucky', 'Chubby', 'Tuffy', 'Whitey', 'Bogie', 'Nikko',
        'Choco', 'Toffee', 'Mocha', 'Pipoy', 'Sky', 'Kimchi', 'Panda', 'Gigi',
    ];

    public function firstNameMale(): string
    {
        return static::randomElement(static::$firstNameMale);
    }

    public function firstNameFemale(): string
    {
        return static::randomElement(static::$firstNameFemale);
    }

    public function firstName($gender = null): string
    {
        if ($gender === 'male') {
            return $this->firstNameMale();
        }

        if ($gender === 'female') {
            return $this->firstNameFemale();
        }

        return static::randomElement(array_merge(static::$firstNameMale, static::$firstNameFemale));
    }

    public function lastName(): string
    {
        return static::randomElement(static::$lastName);
    }

    public function name($gender = null): string
    {
        return $this->firstName($gender).' '.$this->lastName();
    }

    public function petName(): string
    {
        return static::randomElement(static::$petName);
    }
}
