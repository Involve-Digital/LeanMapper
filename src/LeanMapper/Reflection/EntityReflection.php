<?php

/**
 * This file is part of the Lean Mapper library (http://www.leanmapper.com)
 *
 * Copyright (c) 2013 Vojtěch Kohout (aka Tharos)
 *
 * For the full copyright and license information, please view the file
 * license.md that was distributed with this source code.
 */

namespace LeanMapper\Reflection;

use LeanMapper\Exception\InvalidStateException;
use LeanMapper\DefaultEntityReflectionProvider;
use LeanMapper\IEntityReflectionProvider;
use LeanMapper\IMapper;
use ReflectionMethod;

/**
 * Entity reflection
 *
 * @author Vojtěch Kohout
 */
class EntityReflection extends \ReflectionClass
{

    /** @var IMapper|null */
    private $mapper;

    /** @var IEntityReflectionProvider */
    private $entityReflectionProvider;

    /** @var Property[] */
    private $properties = null;

    /** @var array */
    private $getters = null;

    /** @var array */
    private $setters = null;

    /** @var Aliases|null */
    private $aliases;

    /** @var string */
    private $docComment;



    /**
     * @param mixed $argument
     * @param IMapper|null $mapper
     * @param IEntityReflectionProvider|null $entityReflectionProvider
     */
    public function __construct($argument, IMapper $mapper = null, IEntityReflectionProvider $entityReflectionProvider = null)
    {
        parent::__construct($argument);
        $this->mapper = $mapper;
        $this->entityReflectionProvider = $entityReflectionProvider !== null ? $entityReflectionProvider : new DefaultEntityReflectionProvider;
    }



    /**
     * Gets requested entity's property
     *
     * @param string $name
     * @return Property|null
     */
    public function getEntityProperty($name)
    {
        if ($this->properties === null) {
            $this->createProperties();
        }
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }



    /**
     * Gets array of all entity's properties
     *
     * @return Property[]
     */
    public function getEntityProperties()
    {
        if ($this->properties === null) {
            $this->createProperties();
        }
        return $this->properties;
    }



    /**
     * Gets Aliases instance relevant to current class
     *
     * @return Aliases
     */
    public function getAliases()
    {
        if ($this->aliases === null) {
            $this->aliases = AliasesParser::parseSource(file_get_contents($this->getFileName()), $this->getNamespaceName());
        }
        return $this->aliases;
    }



    /**
     * Gets parent entity's reflection
     *
     * @return self|false
     */
    public function getParentClass(): false|EntityReflection
    {
        return ($reflection = parent::getParentClass()) ? new self($reflection->getName(), $this->mapper, $this->entityReflectionProvider) : false;
    }



    /**
     * Gets doc comment of current class
     *
     * @return string
     */
    public function getDocComment(): string
    {
        if ($this->docComment === null) {
            $this->docComment = parent::getDocComment();
        }
        return $this->docComment;
    }



    /**
     * Gets requested getter's reflection
     *
     * @param string $name
     * @return ReflectionMethod|null
     */
    public function getGetter($name)
    {
        if ($this->getters === null) {
            $this->createGetters();
        }
        return isset($this->getters[$name]) ? $this->getters[$name] : null;
    }



    /**
     * Gets array of getter's reflections
     *
     * @return ReflectionMethod[]
     */
    public function getGetters()
    {
        if ($this->getters === null) {
            $this->createGetters();
        }
        return $this->getters;
    }



    /**
     * Gets requested setter's reflection
     *
     * @param string $name
     * @return ReflectionMethod|null
     */
    public function getSetter($name)
    {
        if ($this->setters === null) {
            $this->createSetters();
        }
        return isset($this->setters[$name]) ? $this->setters[$name] : null;
    }

    ////////////////////
    ////////////////////

    /**
     * @throws InvalidStateException
     */
    private function createGetters()
    {
        $this->getters = [];
        $getters = $this->entityReflectionProvider->getGetters($this);

        foreach ($getters as $getter) {
            $name = $getter->getName();
            // collision check
            if (isset($this->getters[$name])) {
                throw new InvalidStateException("Duplicated getter '{$name}' for entity {$this->getName()}.");
            }
            $this->getters[$name] = $getter;
        }
    }


    /**
     * @throws InvalidStateException
     */
    private function createSetters()
    {
        $this->setters = [];
        $setters = $this->entityReflectionProvider->getSetters($this);

        foreach ($setters as $setter) {
            $name = $setter->getName();
            // collision check
            if (isset($this->setters[$name])) {
                throw new InvalidStateException("Duplicated setter '{$name}' for entity {$this->getName()}.");
            }
            $this->setters[$name] = $setter;
        }
    }


    /**
     * @throws InvalidStateException
     */
    private function createProperties()
    {
        $this->properties = [];
        $properties = $this->entityReflectionProvider->getProperties($this, $this->mapper);
        $columns = [];
        foreach ($properties as $property) {
            // collision check
            if (isset($this->properties[$property->getName()])) {
                throw new InvalidStateException(
                    "Duplicated property '{$property->getName()}' in entity {$this->getName()}. Please fix property name."
                );
            }

            $column = $property->getColumn();
            if ($column !== null and $property->isWritable()) {
                if (isset($columns[$column])) {
                    throw new InvalidStateException(
                        "Mapping collision in property '{$property->getName()}' (column '$column') in entity {$this->getName()}. Please fix mapping or make chosen properties read only (using property-read)."
                    );
                }
                $columns[$column] = true;
            }
            $this->properties[$property->getName()] = $property;
        }
    }

}
