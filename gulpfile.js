'use strict';

const del = require('del');
const gulp = require('gulp');
const lec = require('gulp-line-ending-corrector');
const filter = require('gulp-filter');

const sourceDir = 'node_modules/epubjs-reader/reader/**';
const destinationDir = 'asset/vendor/epubjs-reader';

gulp.task('clean', function(done) {
    return del(destinationDir);
});

gulp.task('sync', function (next) {
    const f = filter(['**/*.html', '**/*.js', '**/*.css'], {restore: true});
    gulp.src([sourceDir])
    .pipe(f)
    .pipe(lec())
    .pipe(f.restore)
    .pipe(gulp.dest(destinationDir))
    .on('end', next);
});

gulp.task('default', gulp.series('clean', 'sync'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
