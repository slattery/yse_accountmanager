<?php

namespace Drupal\yse_userdata\Traits;

trait YseProfileRepackagingTrait {

  /**
   * Map userdata response on to YSE specific profile entity
   *
   * YOU WILL PROBABLY NEED TO ADD UID AFTERWARDS!!!
   *
   * This should grow into part of a real yse_detail_profile module...
   *
   * @param array $ysedat
   *   the array returned from lookup.
   *
   * @return array|null
   *   An array ready to add to a new YSE profile object.
   */

  public function profileprep($ysedat) {

    $ysedat = self::primarytypetarget($ysedat);

    if ($ysedat['primary_affiliation'] == 'STUDENT') {
      $ysedat = self::studenttypetarget($ysedat);
    }
    //I need to maybe keep passing YSEDAT around and return it.

    $profileprepped = [];
    $profileprepped['title'] = $ysedat['name'];
    $profileprepped['field_profile_name_surnames'] = $ysedat['lastname'];
    $profileprepped['field_profile_name_given'] = $ysedat['firstname'];
    $profileprepped['field_contact_email'] = $ysedat['email'];
    $profileprepped['field_yse_netid'] = $ysedat['netid'];
    $profileprepped['field_yse_upi'] = $ysedat['upi'];
    $profileprepped["field_student_year_integer"] = $ysedat["year_integer"];
    $profileprepped['status'] = $ysedat['status'];
    $profileprepped['field_yse_alias_shortcut'] = self::slugify($ysedat['name']);

    if (in_array($ysedat['primary_affiliation'], ['STAFF', 'FACULTY', 'EMPLOYEE', 'AFFILIATE'])) {
      $profileprepped['field_profile_position'] = $ysedat['title'];
    }
    if ($ysedat['degree_type_taxref']) {
      $profileprepped['field_student_degree_type_taxref'] = $ysedat['degree_type_taxref'];
    }
    if ($ysedat['degree_xtra_taxref']) {
      $profileprepped['field_student_degree_xtra_taxref'] = $ysedat['degree_xtra_taxref'];
    }
    if ($ysedat['restype_taxref']) {
      $profileprepped['field_primary_restype_taxref'] = $ysedat['restype_taxref'];
    }

    return $profileprepped;
  }

  public function studenttypetarget($ysedat) {
    //joint,jointdegree,degree
    $manager = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    //student_degree_extras
    if (!empty($ysedat["joint"])) {
      $types = $manager->loadTree('student_degrees_extra', 0, NULL, FALSE);
      $xtraterms = [];
      foreach ($types as $term) {
        $xtraterms[strtolower($term->name)] = $term->tid;
      }

      if (!empty($ysedat["jointdegree"])) {
        $jointed = strtolower("Joint: " . $ysedat["jointdegree"]);
        if ($xtraterms[$jointed]) {
          $ysedat['degree_xtra_taxref'] = ['target_id' => $xtraterms[$jointed]];
        }
      }
    }

    //student_degree_types
    if (!empty($ysedat["degree"])) {
      $degreetree = $manager->loadTree('student_degree_types', 0, 2, FALSE);
      $degreeterms = [];
      foreach ($degreetree as $term) {
        // we only want to load 2nd level
        if (!empty($term->parents)) {
          $degreeterms[strtolower($term->name)] = $term->tid;
        }
      }

      if ($ysedat["degree"] && $ysedat["degree"] == "MEM5") {
        $ysedat["degree"] = 'MEM';
      }

      if ($ysedat["degree"] && $degreeterms[strtolower($ysedat["degree"])]) {
        $ysedat['degree_type_taxref'] = ['target_id' => $degreeterms[strtolower($ysedat["degree"])]];
      }
    }

    if ($ysedat["expgraddate"]) {
      $yse4ys = date_parse($ysedat["expgraddate"]); //should always give us YYYY
      $ysedat["year_integer"] = $yse4ys["year"];
    }
    return $ysedat;
  }

  public function primarytypetarget($ysedat) {
    // vocab should be a config setting - in a trait though?
    $manager = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $types = $manager->loadTree('admin_primary_resource_types', 0, NULL, FALSE);
    $typeterms = [];
    foreach ($types as $term) {
      $typeterms[strtolower($term->name)] = $term->tid;
    }
    // $typeterms = ['faculty' => 23, 'student' => 25, 'postdoc' => 812, 'staff' => 24]

    if ($ysedat['title'] && (preg_match("/postdoc/i", $ysedat['title']) || preg_match("/post-doc/i", $ysedat['title']) || preg_match("/post doc/i", $ysedat['title']))) {
      $primarytype = 'postdoc';
    }
    elseif ($ysedat['title'] && (preg_match("/postgrad/i", $ysedat['title']) || preg_match("/post-grad/i", $ysedat['title']) || preg_match("/post grad/i", $ysedat['title']))) {
      $primarytype = 'staff';
    }
    elseif ($ysedat['primary_affiliation'] && in_array($ysedat['primary_affiliation'], ['STAFF', 'FACULTY', 'STUDENT', 'EMPLOYEE', 'AFFILIATE'])) {
      $primarytype = strtolower($ysedat['primary_affiliation']);
    }

    $primarytarg = $primarytype ? $typeterms[$primarytype] : NULL;

    $ysedat['status'] = $primarytarg ? 1 : 0;

    if ($primarytarg) {
      $ysedat['restype_taxref'] = ['target_id' => $primarytarg];
    }
    return $ysedat;
  }

  /**
   * slugify
   *
   * @param string $str
   *    the string needing slugifying.
   *
   * @return string|null
   *    the slugged string.
   *
   */

  protected function slugify($string) {
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^a-z0-9- ]/i', '', $string);
    $string = str_replace(' ', '-', $string);
    $string = trim($string, '-');
    $string = strtolower($string);
    return $string;
  }

}


