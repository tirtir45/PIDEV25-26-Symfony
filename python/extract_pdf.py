import sys
import re
from PyPDF2 import PdfReader


class PdfTextExtractor:

    @staticmethod
    def extract_text(pdf_path):

        reader = PdfReader(pdf_path)

        text = ""

        for page in reader.pages:
            page_text = page.extract_text()

            if page_text:
                text += page_text + "\n"

        # Nettoyage du texte
        text = re.sub(r'\s+', ' ', text).strip()

        return text


if __name__ == "__main__":

    if len(sys.argv) < 2:
        print("Usage: python extract_pdf.py fichier.pdf")
        sys.exit(1)

    pdf_path = sys.argv[1]

    try:
        extracted_text = PdfTextExtractor.extract_text(pdf_path)

        print(extracted_text)

    except Exception as e:
        print(f"Erreur : {str(e)}")