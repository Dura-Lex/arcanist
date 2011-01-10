<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

abstract class ArcanistRepositoryAPI {

  const FLAG_MODIFIED     = 1;
  const FLAG_ADDED        = 2;
  const FLAG_DELETED      = 4;
  const FLAG_UNTRACKED    = 8;
  const FLAG_CONFLICT     = 16;
  const FLAG_MISSING      = 32;
  const FLAG_UNSTAGED     = 64;
  const FLAG_UNCOMMITTED  = 128;
  const FLAG_EXTERNALS    = 256;

  // Occurs in SVN when you replace a file with a directory.
  const FLAG_OBSTRUCTED   = 512;

  protected $path;
  protected $diffLinesOfContext = 0x7FFF;

  abstract public function getSourceControlSystemName();

  public function getDiffLinesOfContext() {
    return $this->diffLinesOfContext;
  }

  public function setDiffLinesOfContext($lines) {
    $this->diffLinesOfContext = $lines;
    return $this;
  }

  public static function newAPIFromWorkingCopyIdentity(
    ArcanistWorkingCopyIdentity $working_copy) {

    $root = $working_copy->getProjectRoot();

    if (!$root) {
      throw new ArcanistUsageException(
        "There is no readable '.arcconfig' file in the working directory or ".
        "any parent directory. Create an '.arcconfig' file to configure arc.");
    }

    if (@file_exists($root.'/.svn')) {
      phutil_require_module('arcanist', 'repository/api/subversion');
      return new ArcanistSubversionAPI($root);
    }

    $git_root = self::discoverGitBaseDirectory($root);
    if ($git_root) {
      if (!Filesystem::pathsAreEquivalent($root, $git_root)) {
        throw new ArcanistUsageException(
          "'.arcconfig' file is located at '{$root}', but working copy root ".
          "is '{$git_root}'. Move '.arcconfig' file to the working copy root.");
      }
      phutil_require_module('arcanist', 'repository/api/git');
      return new ArcanistGitAPI($root);
    }

    throw new ArcanistUsageException(
      "The current working directory is not part of a working copy for a ".
      "supported version control system (svn or git).");
  }

  protected function __construct($path) {
    $this->path = $path;
  }

  public function getPath($to_file = null) {
    if ($to_file !== null) {
      return $this->path.'/'.ltrim($to_file, '/');
    } else {
      return $this->path.'/';
    }
  }

  public function getUntrackedChanges() {
    return $this->getWorkingCopyFilesWithMask(self::FLAG_UNTRACKED);
  }

  public function getUnstagedChanges() {
    return $this->getWorkingCopyFilesWithMask(self::FLAG_UNSTAGED);
  }

  public function getUncommittedChanges() {
    return $this->getWorkingCopyFilesWithMask(self::FLAG_UNCOMMITTED);
  }

  public function getMergeConflicts() {
    return $this->getWorkingCopyFilesWithMask(self::FLAG_CONFLICT);
  }

  private function getWorkingCopyFilesWithMask($mask) {
    $match = array();
    foreach ($this->getWorkingCopyStatus() as $file => $flags) {
      if ($flags & $mask) {
        $match[] = $file;
      }
    }
    return $match;
  }

  private static function discoverGitBaseDirectory($root) {
    try {
      list($stdout) = execx(
        '(cd %s; git rev-parse --show-cdup)',
        $root);
      return Filesystem::resolvePath(rtrim($stdout, "\n"), $root);
    } catch (CommandException $ex) {
      if (preg_match('/^fatal: Not a git repository/', $ex->getStdErr())) {
        return null;
      }
      throw $ex;
    }
  }

  abstract public function getBlame($path);
  abstract public function getWorkingCopyStatus();
  abstract public function getRawDiffText($path);

}