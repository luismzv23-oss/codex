"""Local PDF extraction. JSON on stdout; original PDF arrives through stdin."""
import sys, json, io, contextlib, threading, os

def ocr_image(image, engine=None):
    import numpy as np
    from rapidocr_onnxruntime import RapidOCR
    if engine is None:
        engine = RapidOCR(intra_op_num_threads=2, inter_op_num_threads=2)
    image.thumbnail((2400, 2400))
    results, _ = engine(np.asarray(image.convert("RGB")))
    lines = []
    for box, word, score in sorted(results or [], key=lambda r: min(p[1] for p in r[0])):
        y = sum(p[1] for p in box) / 4
        height = max(p[1] for p in box) - min(p[1] for p in box)
        if lines and abs(lines[-1][0] - y) < max(8, height * .6):
            lines[-1][1].append((min(p[0] for p in box), word))
        else:
            lines.append((y, [(min(p[0] for p in box), word)]))
    return "\n".join("  ".join(word for _, word in sorted(words)) for _, words in lines), engine

def extract(data):
    if not data.startswith(b"%PDF-"):
        from PIL import Image, ImageOps
        Image.MAX_IMAGE_PIXELS = 20000000
        with Image.open(io.BytesIO(data)) as original:
            if original.format not in ("JPEG", "PNG") or original.width * original.height > 20000000:
                raise ValueError("Imagen no admitida o superior a 20 megapixeles.")
            image = ImageOps.exif_transpose(original)
            text, _ = ocr_image(image)
            return {"text": text[:200000], "pages": 1, "methods": ["ocr"]}

    from pypdf import PdfReader
    import pypdfium2 as pdfium
    reader = PdfReader(io.BytesIO(data))
    if reader.is_encrypted:
        raise ValueError("El PDF tiene una contraseña. Sube una copia desbloqueada.")
    if not 1 <= len(reader.pages) <= 20:
        raise ValueError("El PDF debe tener entre 1 y 20 páginas.")
    text, methods, engine = [], [], None
    with pdfium.PdfDocument(data) as document:
        for i, page in enumerate(reader.pages):
            value = page.extract_text(extraction_mode="layout") or ""
            if len(value.strip()) < 40:
                if engine is None:
                    from rapidocr_onnxruntime import RapidOCR
                    engine = RapidOCR(intra_op_num_threads=2, inter_op_num_threads=2)
                render_page = document[i]
                w, h = render_page.get_size()
                scale = min(2.0, 2400 / max(w, h))
                bitmap = render_page.render(scale=scale)
                image = bitmap.to_pil()
                value, engine = ocr_image(image, engine)
                image.close(); bitmap.close(); render_page.close()
                methods.append("ocr")
            else:
                methods.append("text")
            text.append(value[:50000])
    return {"text": "\n".join(text)[:200000], "pages": len(reader.pages), "methods": methods}

def preview(data, page_number):
    from PIL import Image, ImageOps
    import pypdfium2 as pdfium
    if data.startswith(b"%PDF-"):
        with pdfium.PdfDocument(data) as document:
            if len(document) > 20 or not 1 <= page_number <= len(document):
                raise ValueError("Pagina fuera de rango")
            page = document[page_number - 1]
            w, h = page.get_size()
            bitmap = page.render(scale=min(2.0, 1800 / max(w,h)))
            image = bitmap.to_pil()
            output = io.BytesIO(); image.save(output, format="PNG")
            image.close(); bitmap.close(); page.close()
            return output.getvalue()
    Image.MAX_IMAGE_PIXELS = 20000000
    with Image.open(io.BytesIO(data)) as original:
        if original.width * original.height > 20000000: raise ValueError("Imagen demasiado grande")
        image = ImageOps.exif_transpose(original); image.thumbnail((1800,1800))
        output = io.BytesIO(); image.save(output, format="PNG");return output.getvalue()

if __name__ == "__main__":
    watchdog = threading.Timer(85, lambda: os._exit(124))
    watchdog.daemon = True
    watchdog.start()
    try:
        data = sys.stdin.buffer.read(10 * 1024 * 1024 + 1)
        if len(data) > 10 * 1024 * 1024:
            raise ValueError("PDF inválido o demasiado grande.")
        if len(sys.argv) == 3 and sys.argv[1] == "--preview":
            result = preview(data, int(sys.argv[2])); sys.stdout.buffer.write(result); sys.exit(0)
        with contextlib.redirect_stdout(sys.stderr):
            result = extract(data)
        print(json.dumps(result, ensure_ascii=True))
    except Exception as error:
        print(json.dumps({"error": str(error)[:250]}, ensure_ascii=True))
        sys.exit(1)
