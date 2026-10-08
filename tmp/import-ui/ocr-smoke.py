from pathlib import Path
import subprocess, json
from PIL import Image, ImageDraw, ImageFont
from pypdf import PdfWriter
from pypdf.generic import DictionaryObject, NameObject, DecodedStreamObject
root=Path.cwd(); out=root/'tmp/import-ui'
image=Image.new('RGB',(1400,1000),'white'); draw=ImageDraw.Draw(image);font=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',34)
lines=['FACTURA A 00001-00000007','Proveedor Ejemplo','CUIT: 30-12345678-9','Fecha: 05/10/2026','SKU1 Producto     2     100,00     21     242,00','TOTAL 242,00']
for i,line in enumerate(lines): draw.text((50,60+i*100),line,font=font,fill='black')
for extension in ['png','jpg','jpeg']: image.save(out/f'fixture.{extension}')
image.save(out/'scan.pdf','PDF',resolution=120)
writer=PdfWriter();page=writer.add_blank_page(612,792)
font_ref=writer._add_object(DictionaryObject({NameObject('/Type'):NameObject('/Font'),NameObject('/Subtype'):NameObject('/Type1'),NameObject('/BaseFont'):NameObject('/Helvetica')}))
page[NameObject('/Resources')]=DictionaryObject({NameObject('/Font'):DictionaryObject({NameObject('/F1'):font_ref})})
stream=DecodedStreamObject();commands=['BT /F1 12 Tf 40 750 Td']
for i,line in enumerate(lines):
 if i: commands.append('0 -35 Td')
 commands.append('('+line+') Tj')
commands.append('ET');stream.set_data(('\n'.join(commands)).encode('ascii'));page[NameObject('/Contents')]=writer._add_object(stream)
with (out/'native.pdf').open('wb') as file:writer.write(file)
for name in ['fixture.png','fixture.jpg','fixture.jpeg','scan.pdf','native.pdf']:
 result=subprocess.run([str(root/'.venv-pdf/Scripts/python.exe'),str(root/'scripts/pdf/extract.py')],input=(out/name).read_bytes(),capture_output=True,timeout=100)
 data=json.loads(result.stdout);assert '242' in data.get('text',''), data
 print(name, data['methods'], 'OK',flush=True)
