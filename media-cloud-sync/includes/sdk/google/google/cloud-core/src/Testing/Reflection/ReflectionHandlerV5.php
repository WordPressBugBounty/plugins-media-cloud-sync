<?php

/**
 * Copyright 2022 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Dudlewebs\WPMCS\Google\Cloud\Core\Testing\Reflection;

use Dudlewebs\WPMCS\Google\Cloud\Core\Testing\Reflection\DescriptionFactory as CoreDescriptionFactory;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\DocBlock;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\DocBlock\DescriptionFactory;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\DocBlock\StandardTagFactory;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\DocBlockFactory;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\File\LocalFile;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\FqsenResolver;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\NodeVisitor\ElementNameResolver;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\Php\Factory;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\Php\Factory\Noop;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\Php\Factory\TraitUse;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\Php\NodesFactory;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\Php\ProjectFactory;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\Php\ProjectFactoryStrategies;
use Dudlewebs\WPMCS\phpDocumentor\Reflection\TypeResolver;
use Dudlewebs\WPMCS\PhpParser\Lexer;
use Dudlewebs\WPMCS\PhpParser\NodeTraverser;
use Dudlewebs\WPMCS\PhpParser\NodeVisitor\NameResolver;
use Dudlewebs\WPMCS\PhpParser\ParserFactory;
use Dudlewebs\WPMCS\PhpParser\PrettyPrinter\Standard as PrettyPrinter;
/**
 * Class for running snippets using phpdocumentor/reflection:v5.
 *
 * @internal
 */
class ReflectionHandlerV5
{
    private $descriptionFactory;
    private $docBlockFactory;
    public function __construct()
    {
        $this->descriptionFactory = $this->createDescriptionFactory();
        $this->docBlockFactory = $this->createDocBlockFactory($this->descriptionFactory);
    }
    /**
     * @param string $class
     * @return DocBlock
     */
    public function createDocBlock($classOrMethod)
    {
        return $this->docBlockFactory->create($classOrMethod);
    }
    /**
     * @param DocBlock $docBlock
     * @return string
     */
    public function getDocBlockText(DocBlock $docBlock)
    {
        $description = $this->descriptionFactory->create($docBlock->getSummary() . "\n\n" . $docBlock->getDescription(), $docBlock->getContext());
        return $description->render();
    }
    /**
     * @param array $files
     * @return string[]
     */
    public function classes(array $files)
    {
        $projectFactory = $this->createProjectFactory();
        $localFiles = [];
        foreach ($files as $file) {
            $localFiles[] = new LocalFile($file);
        }
        $project = $projectFactory->create('My Project', $localFiles);
        $classes = [];
        foreach ($files as $file) {
            $classesInFile = $project->getFiles()[$file]->getClasses();
            foreach ($classesInFile as $class) {
                $classes[] = (string) $class->getFqsen();
            }
        }
        return $classes;
    }
    /**
     * @return ProjectFactory
     */
    public function createProjectFactory()
    {
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7, new Lexer\Emulative(['phpVersion' => Lexer\Emulative::PHP_8_0]));
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new NameResolver());
        $nodeTraverser->addVisitor(new ElementNameResolver());
        $nodesFactory = new NodesFactory($parser, $nodeTraverser);
        $docblockFactory = $this->createDocBlockFactory($this->createDescriptionFactory());
        $methodStrategy = new Factory\Method($docblockFactory);
        $strategies = new ProjectFactoryStrategies([new Factory\Namespace_(), new Factory\Argument(new PrettyPrinter()), new Factory\Class_($docblockFactory), new Factory\Enum_($docblockFactory), new Factory\EnumCase($docblockFactory, new PrettyPrinter()), new Factory\Define($docblockFactory, new PrettyPrinter()), new Factory\GlobalConstant($docblockFactory, new PrettyPrinter()), new Factory\ClassConstant($docblockFactory, new PrettyPrinter()), new Factory\File($docblockFactory, $nodesFactory), new Factory\Function_($docblockFactory), new Factory\Interface_($docblockFactory), $methodStrategy, new Factory\Property($docblockFactory, new PrettyPrinter()), new Factory\Trait_($docblockFactory), new Factory\IfStatement(), new TraitUse()]);
        $strategies->addStrategy(new Factory\ConstructorPromotion($methodStrategy, $docblockFactory, new PrettyPrinter()), 1100);
        $strategies->addStrategy(new Noop(), -\PHP_INT_MAX);
        return new ProjectFactory($strategies);
    }
    /**
     * @return DescriptionFactory
     */
    public function createDescriptionFactory()
    {
        $fqsenResolver = new FqsenResolver();
        $tagFactory = new StandardTagFactory($fqsenResolver);
        $descriptionFactory = new CoreDescriptionFactory($tagFactory);
        $tagFactory->addService($descriptionFactory, DescriptionFactory::class);
        $tagFactory->addService(new TypeResolver($fqsenResolver));
        return $descriptionFactory;
    }
    /**
     * @return DocBlockFactory
     */
    private function createDocBlockFactory($descriptionFactory)
    {
        $tagFactory = $descriptionFactory->getTagFactory();
        return new DocBlockFactory($descriptionFactory, $tagFactory);
    }
}
