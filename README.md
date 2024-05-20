Subcourse module for Moodle
===========================

This Moodle module provides very simple yet useful functionality. When added into a
course, it behaves as a graded activity. The grade for each student is took from a
final grade in another course. Combined with
[metacourses](http://docs.moodle.org/en/Course_meta_link), this allows course
designers to effectively organise courses into separate units.

Usage
-----

* Create a main course and one or few other courses that should act as a sub-courses of
  the main course.
* Into the main course, add a new instance of the Subcourse activity module for each
  of the referenced course (sub-course) to fetch grades from.
* Enrol students into all courses (the main one as well as the referenced ones).
* Let students receive the final grades in the referenced courses.
* Check that the final grade in the referenced courses now appears as the grade for
  the Subcourse activity in the main course.
  
Support
------

Free support for this plugin is available in the moodle.org forums - https://moodle.org/mod/forum/view.php?id=44

Commercial level support is available from the Moodle Partner Catalyst IT https://www.catalyst.net.nz/contact-us

Author
------

The module was originally written by David Mudrák <david@moodle.com> and is now maintained by Catalyst IT.

Useful links
------------

* [Bug tracker](https://github.com/EduardoKrausME/moodle-mod_subcourse/issues)

License
-------

This program is free software: you can redistribute it and/or modify it under the
terms of the GNU General Public License as published by the Free Software Foundation,
either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this
program. If not, see <http://www.gnu.org/licenses/>.
