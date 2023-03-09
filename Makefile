.PHONY: install serve

install:
	bundle install
	composer install

serve:
	bundle exec jekyll serve -wl
