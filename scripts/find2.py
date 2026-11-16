p = r'C:\\xampp\\htdocs\\trendsham\\resources\\views\\customer\\orders\\show.blade.php'
s = open(p, encoding='utf-8').read()
i = s.find('shipment')
start = s.rfind('@if', 0, i)
print('start', start)
print(repr(s[start:start+30]))