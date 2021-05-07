const gulp = require('gulp');
const plumber = require('gulp-plumber');
const sass = require('gulp-sass');
const uglify = require('gulp-uglify');
const include = require('gulp-include');
const autoprefixer = require('gulp-autoprefixer');
const notify = require('gulp-notify');
const cleanCSS = require('gulp-clean-css');
const rename = require('gulp-rename');
const imagemin = require('gulp-imagemin');

// only for OperatorBundle
const publicPath = 'Resources/public/'

const paths = {
    src: {
        styles: publicPath + 'src/scss/*.scss',
        scripts: publicPath + 'src/js/**/*.js',
        images: publicPath + 'src/img/**/*'
    },
    dist: {
        styles: publicPath + 'dist/css',
        scripts: publicPath + 'dist/js',
        images: publicPath + 'dist/img'
    },
    watch: {
        styles: publicPath + 'src/scss/**/*.scss',
        scripts: publicPath + 'src/js/**/*.js',
        images: publicPath + 'src/img/**/*'
    },
};

const styles = function () {
    return gulp.src(paths.src.styles)
        .pipe(plumber({errorHandler: notify.onError('Error: <%= error.message %>')}))
        .pipe(sass())
        .pipe(autoprefixer({
            cascade: false
        }))
        .pipe(cleanCSS({format: "beautify"}))
        .pipe(gulp.dest(paths.dist.styles));
};
exports.styles = styles;

const minifyCss = function () {
    return gulp.src(paths.src.styles)
        .pipe(plumber())
        .pipe(sass())
        .pipe(autoprefixer({
            cascade: false
        }))
        .pipe(cleanCSS())
        .pipe(rename({extname: '.min.css'}))
        .pipe(gulp.dest(paths.dist.styles));
};
exports.minify_css = minifyCss;

const scripts = function () {
    return gulp.src(paths.src.scripts)
        .pipe(include())
        .pipe(uglify())
        .pipe(gulp.dest(paths.dist.scripts));
};
exports.scripts = scripts;

// read and optimize images
const images = function () {
    return gulp.src(paths.src.images)
        .pipe(imagemin({
            progressive: true,
        }))
        .pipe(gulp.dest(paths.dist.images));
};
exports.images = images;

const watch = function (done) {
    gulp.watch(paths.watch.styles, gulp.series([styles, minifyCss]));
    gulp.watch(paths.watch.scripts, gulp.series([scripts]));
    done();
};

exports.default = gulp.parallel([watch]);
exports.deploy = gulp.series([minifyCss, scripts, images]);
