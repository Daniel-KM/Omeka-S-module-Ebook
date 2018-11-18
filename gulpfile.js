'use strict';

const del = require('del');
const gulp = require('gulp');
const gulpif = require('gulp-if');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const lec = require('gulp-line-ending-corrector');
const filter = require('gulp-filter');

const bundle = [
    {
        'source': 'node_modules/epubjs-reader/reader/**',
        'dest': 'asset/vendor/epubjs-reader',
    },
];

gulp.task('clean', function(done) {
    bundle.forEach(function (module) {
        return del.sync(module.dest);
    });
    done();
});

gulp.task('sync', function (done) {
    const filtering = filter(['**/*.html', '**/*.js', '**/*.css'], {restore: true});
    bundle.forEach(function (module) {
        gulp.src(module.source)
            .pipe(filtering)
            .pipe(lec())
            .pipe(filtering.restore)
            .pipe(gulpif(module.rename, rename({suffix:'.min'})))
            .pipe(gulpif(module.uglify, uglify()))
            .pipe(gulp.dest(module.dest));
    });
    done();
});

gulp.task('default', gulp.series('clean', 'sync'));

gulp.task('install', gulp.task('default'));

gulp.task('update', gulp.task('default'));
