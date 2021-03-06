<?php
// Copyright 2004-present Facebook. All Rights Reserved.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

/**
 * Execute mouse commands for RemoteWebDriver.
 */
class RemoteMouse implements WebDriverMouse {

  private $executor;

  public function __construct($executor) {
    $this->executor = $executor;
  }

  /**
   * @return WebDriverMouse
   */
  public function click(WebDriverCoordinates $where = null) {
    $this->moveIfNeeded($where);
    $this->executor->execute('mouseClick', array(
      'button' => 0,
    ));
    return $this;
  }

  /**
   * @return WebDriverMouse
   */
  public function contextClick(WebDriverCoordinates $where = null) {
    $this->moveIfNeeded($where);
    $this->executor->execute('mouseClick', array(
      'button' => 2,
    ));
    return $this;
  }

  /**
   * @return WebDriverMouse
   */
  public function doubleClick(WebDriverCoordinates $where = null) {
    $this->moveIfNeeded($where);
    $this->executor->execute('mouseDoubleClick');
    return $this;
  }

  /**
   * @return WebDriverMouse
   */
  public function mouseDown(WebDriverCoordinates $where = null) {
    $this->moveIfNeeded($where);
    $this->executor->execute('mouseButtonDown');
    return $this;
  }

  /**
   * @return WebDriverMouse
   */
  public function mouseMove(WebDriverCoordinates $where = null,
                            $x_offset = null,
                            $y_offset = null) {
    $params = array();
    if ($where !== null) {
      $params['element'] = $where->getAuxiliary();
    }
    if ($x_offset !== null) {
      $params['xoffset'] = $x_offset;
    }
    if ($y_offset !== null) {
      $params['yoffset'] = $y_offset;
    }
    $this->executor->execute('mouseMoveTo', $params);
    return $this;
  }

  /**
   * @return WebDriverMouse
   */
  public function mouseUp(WebDriverCoordinates $where = null) {
    $this->moveIfNeeded($where);
    $this->executor->execute('mouseButtonUp');
    return $this;
  }

  /**
   * @return void
   */
  protected function moveIfNeeded(WebDriverCoordinates $where = null) {
    if ($where) {
      $this->mouseMove($where);
    }
  }
}
