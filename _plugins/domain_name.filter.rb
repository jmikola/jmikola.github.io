# https://github.com/lawrencewoodman/domain_name-liquid_filter
require 'liquid'

module DomainNameFilter
  def domain_name(url)
    return url.sub(%r{([a-z]+://)?([^/]*)(/.*$)?}i, '\\2')
  end
end

Liquid::Template.register_filter(DomainNameFilter)
