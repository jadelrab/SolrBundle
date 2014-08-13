<?php
namespace FS\SolrBundle\Doctrine\Annotation;

use Doctrine\Common\Annotations\AnnotationReader as Reader;

class AnnotationReader
{

    /**
     * @var Reader
     */
    private $reader;

    const DOCUMENT_CLASS = 'FS\SolrBundle\Doctrine\Annotation\Document';
    const FIELD_CLASS = 'FS\SolrBundle\Doctrine\Annotation\Field';
    const FIELD_IDENTIFIER_CLASS = 'FS\SolrBundle\Doctrine\Annotation\Id';
    const DOCUMENT_INDEX_CLASS = 'FS\SolrBundle\Doctrine\Annotation\Document';
    const SYNCHRONIZATION_FILTER_CLASS = 'FS\SolrBundle\Doctrine\Annotation\SynchronizationFilter';

    public function __construct()
    {
        $this->reader = new Reader();
    }

    /**
     * reads the entity and returns a set of annotations
     *
     * @param string $entity
     * @param string $type
     * @return array
     */
    private function getPropertiesByType($entity, $type)
    {
        $reflectionClass = new \ReflectionClass($entity);
        $properties = $reflectionClass->getProperties();

        $fields = array();
        foreach ($properties as $property) {
            $annotation = $this->reader->getPropertyAnnotation($property, $type);

            if (null === $annotation) {
                continue;
            }

            $property->setAccessible(true);
            $annotation->value = $property->getValue($entity);
            $annotation->name = $property->getName();

            $fields[] = $annotation;
        }

        return $fields;
    }

    /**
     * reads the entity and returns a set of annotations
     *
     * @param string $entity
     * @param string $type
     * @throws AnnotationException
     * @return array
     */
    private function getMethodsByType($entity, $type)
    {
          $reflectionClass = new \ReflectionClass($entity);
          $methods = $reflectionClass->getMethods();

          $fields = array();
          foreach ($methods as $method) {
                $annotation = $this->reader->getMethodAnnotation($method, $type);

                if (null === $annotation) {
                    continue;
                }

                if (!$method->isPublic()) {
                    throw new AnnotationException(sprintf('Method "%s" in class "%s" is not callabe. Change visibility from %s to %s.',
                        $method->getName(),
                        $method->getDeclaringClass(),
                        $method->isPrivate() ? 'private' : 'protected'
                    ));
                }

                $annotation->value = $method->invoke($entity);
                if ($annotation->name == '') {
                    $annotation->name = $method->getName();
                }

                $fields[] = $annotation;
          }

          return $fields;
    }

    /**
     * @param object $entity
     * @return array
     */
    public function getFields($entity)
    {
        return array_merge(
            $this->getPropertiesByType($entity, self::FIELD_CLASS),
            $this->getMethodsByType($entity, self::FIELD_CLASS)
        );
    }

    /**
     * @param object $entity
     * @throws \InvalidArgumentException if the boost value is not numeric
     * @return number
     */
    public function getEntityBoost($entity)
    {
        $annotation = $this->getClassAnnotation($entity, self::DOCUMENT_INDEX_CLASS);

        if (!$annotation instanceof Document) {
            return 0;
        }

        try {
            $boostValue = $annotation->getBoost();
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf($e->getMessage() . ' for entity %s', get_class($entity)));
        }

        return $boostValue;
    }

    /**
     * @param object $entity
     * @return Type
     * @throws \RuntimeException
     */
    public function getIdentifier($entity)
    {
        $id = $this->getPropertiesByType($entity, self::FIELD_IDENTIFIER_CLASS);

        if (count($id) == 0) {
            throw new \RuntimeException('no identifer declared in entity ' . get_class($entity));
        }

        return reset($id);
    }

    /**
     * @param object $entity
     * @return string classname of repository
     */
    public function getRepository($entity)
    {
        $annotation = $this->getClassAnnotation($entity, self::DOCUMENT_CLASS);

        if ($annotation instanceof Document) {
            return $annotation->repository;
        }

        return '';
    }

    /**
     * returns all fields and field for idendification
     *
     * @param object $entity
     * @return array
     */
    public function getFieldMapping($entity)
    {
        $fields = $this->getFields($entity);

        $mapping = array();
        foreach ($fields as $field) {
            if ($field instanceof Field) {
                $mapping[$field->getNameWithAlias()] = $field->name;
            }
        }

        $id = $this->getIdentifier($entity);
        $mapping['id'] = $id->name;

        return $mapping;
    }

    /**
     * @param object $entity
     * @return boolean
     */
    public function hasDocumentDeclaration($entity)
    {
        $annotation = $this->getClassAnnotation($entity, self::DOCUMENT_INDEX_CLASS);

        return $annotation !== null;
    }

    /**
     * @param string $entity
     * @return string
     */
    public function getSynchronizationCallback($entity)
    {
        $annotation = $this->getClassAnnotation($entity, self::SYNCHRONIZATION_FILTER_CLASS);

        if (!$annotation) {
            return '';
        }

        return $annotation->callback;
    }

    /**
     * @param string $entity
     * @param string $annotation
     * @return string
     */
    private function getClassAnnotation($entity, $annotation)
    {
        $reflectionClass = new \ReflectionClass($entity);

        return $this->reader->getClassAnnotation($reflectionClass, $annotation);
    }
}
