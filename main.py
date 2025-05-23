from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options



# Configuración de Selenium para usar Chrome en modo headless
chrome_options = Options()
chrome_options.add_argument("--headless")
driver = webdriver.Chrome(options=chrome_options)

# URL objetivo
url = "https://inmobaperu.com/departamentos"  # Cambia esto por la URL que deseas scrapear

# Abrir la página
driver.get(url)
pagination = driver.find_element(By.CSS_SELECTOR, "ul.pagination")

print(pagination)
# Imprimir el DOM
#print(dom)

# Cerrar el navegador
#driver.quit()

input("presiona para cerrar el navegador:")