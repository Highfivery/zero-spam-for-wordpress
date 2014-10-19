# Require any additional compass plugins here.

# Set this to the root of your project when deployed:
http_path = (environment == :production ? "../" : "../")
css_dir = (environment == :production ? "build/css" : "build/css-dev")
sass_dir = "src/scss"
images_dir = (environment == :production ? "img" : "img-dev")
javascripts_dir = (environment == :production ? "build/css" : "build/js-dev")
generated_images_dir = (environment == :production ? "build/img" : "build/img-dev")
images_path =  "src/img"

# You can select your preferred output style here (can be overridden via the command line):
# output_style = :expanded or :nested or :compact or :compressed
output_style = (environment == :production ? :compressed : :expanded)

# To enable relative paths to assets via compass helper functions. Uncomment:
# relative_assets = true

# To disable debugging comments that display the original location of your selectors. Uncomment:
# line_comments = false


# If you prefer the indented syntax, you might want to regenerate this
# project again passing --syntax sass, or you can uncomment this:
# preferred_syntax = :sass
# and then run:
# sass-convert -R --from scss --to sass sass scss && rm -rf sass && mv scss sass
