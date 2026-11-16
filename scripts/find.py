p = r'C:\\xampp\\htdocs\\trendsham\\resources\\views\\customer\\orders\\show.blade.php'
s = open(p, encoding='utf-8').read()
i = s.find('shipment')
print('first shipment at', i)
print(repr(s[i-20:i+40]))