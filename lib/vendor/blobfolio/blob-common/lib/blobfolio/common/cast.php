<?php
 namespace blobfolio\wp\wh\vendor\common; class cast { public static function to_array($value=null) { return static::array($value); } public static function array($value=null) { ref\cast::array($value); return $value; } public static function array_type(&$arr=null) { if (! \is_array($arr) || ! \count($arr)) { return false; } $keys = \array_keys($arr); if (\range(0, \count($keys) - 1) === $keys) { return 'sequential'; } elseif (\count($keys) === \count(\array_filter($keys, 'is_numeric'))) { return 'indexed'; } else { return 'associative'; } } public static function to_bool($value=false, bool $flatten=false) { return static::bool($value, $flatten); } public static function bool($value=false, bool $flatten=false) { ref\cast::bool($value, $flatten); return $value; } public static function boolean($value=false, bool $flatten=false) { return static::bool($value, $flatten); } public static function to_float($value=0, bool $flatten=false) { return static::float($value, $flatten); } public static function double($value=0, bool $flatten=false) { return static::float($value, $flatten); } public static function float($value=0, bool $flatten=false) { ref\cast::float($value, $flatten); return $value; } public static function to_int($value=0, bool $flatten=false) { return static::int($value, $flatten); } public static function int($value=0, bool $flatten=false) { ref\cast::int($value, $flatten); return $value; } public static function integer($value=0, bool $flatten=false) { return static::int($value, $flatten); } public static function to_number($value=0, bool $flatten=false) { return static::number($value, $flatten); } public static function number($value=0, bool $flatten=false) { ref\cast::number($value, $flatten); return $value; } public static function to_string($value='', bool $flatten=false) { return static::string($value, $flatten); } public static function string($value='', bool $flatten=false) { ref\cast::string($value, $flatten); return $value; } public static function to_type($value, string $type='', bool $flatten=false) { ref\cast::to_type($value, $type, $flatten); return $value; } } 