# Jail Release Data Analysis System

A dockerized PHP application for analyzing jail release data from multiple CSV files. Provides trend analysis, interactive searching, and visual data exploration across hundreds of daily release records.

## Features

- **Memory-Optimized Processing**: Handles 400+ CSV files with streaming data processing
- **Comprehensive Trend Analysis**:
  - Total releases vs unique individuals
  - Bond amount analysis and ranges
  - Top offenses, courts, attorneys, and bond types
  - Monthly and daily release patterns
  - Configurable top N displays (5, 10, 20, 50, 100)
- **Interactive Search**: Multi-criteria search across all records
- **Smart Caching**: 1-hour cache for instant subsequent loads
- **Docker Deployment**: Fully containerized with Docker Compose

## Screenshots

### Dashboard Overview
Shows total release records, unique individuals, unique cases, average bonds, and offense types across all tracked months.

### Trend Visualizations
Interactive bar charts for offenses, courts, attorneys, bond types, and busiest release days with selectable top N counts.

### Search Interface
Multi-field search with date picker and partial date matching.

## Prerequisites

- Docker
- Docker Compose
- CSV files with the following columns:
  - SONumber, InmateName, Address1, CaseNumber, Court
  - AttorneyName, ReleaseDate, ReleaseTime, ReleaseType
  - OffenseDescription, OffenseDate, BondType, BondAmount, BondsmanName

## Installation

1. Clone or download this repository:
```bash
mkdir jail-analyzer
cd jail-analyzer
```

2. Create the required files:
   - `Dockerfile`
   - `docker-compose.yml`
   - `docker-entrypoint.sh`
   - `.dockerignore`
   - `index.php`

3. Make the entrypoint script executable:
```bash
chmod +x docker-entrypoint.sh
```

4. Create a data directory and add your CSV files:
```bash
mkdir data
cp /path/to/your/*.csv data/
```

5. Build and start the container:
```bash
docker-compose up -d --build
```

6. Access the application:
```
http://localhost:8080
```

## Project Structure

```
jail-analyzer/
├── Dockerfile
├── docker-compose.yml
├── docker-entrypoint.sh
├── .dockerignore
├── index.php
├── data/
│   ├── JAReleases_20251002.csv
│   ├── JAReleases_20251001.csv
│   └── ... (more CSV files)
└── README.md
```

## Usage

### Initial Data Load

1. Open `http://localhost:8080` in your browser
2. Click the "Start Loading Data" button
3. Wait 2-5 minutes for initial processing (depending on file count)
4. Data is cached for 1 hour after loading

### Viewing Trends

- Navigate to the **Trends** tab
- Use dropdown selectors to view Top 5, 10, 20, 50, or 100 items
- Scroll through various trend categories:
  - Overview statistics
  - Bond amount ranges
  - Top offenses
  - Top courts
  - Top attorneys
  - Top bond types
  - Busiest release days
  - Release types
  - Monthly trends

### Searching Records

1. Go to the **Search** tab
2. Enter search criteria (supports partial matching):
   - Inmate Name
   - Offense Description
   - Court
   - Attorney Name
   - Release Type
   - Bond Type
   - Release Date (use date picker or enter partial dates like "2025-10")
3. Click "Search"
4. Results limited to 100 records for performance

### Managing Data

- **Reload Data**: Forces fresh data load from all CSV files
- **Clear Cache**: Removes cached data (next load will reprocess files)

## Configuration

### Change Port

Edit `docker-compose.yml`:
```yaml
ports:
  - "8080:80"  # Change 8080 to your desired port
```

### Adjust Memory Limits

Edit `Dockerfile`:
```dockerfile
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini
```

### Modify Cache Duration

Edit `index.php`:
```php
private $cacheFile = '/tmp/jail_analyzer_cache.json';
// Change 3600 (1 hour) to desired seconds
if (!$forceReload && file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile) < 3600))
```

## Docker Commands

```bash
# Start the container
docker-compose up -d

# Stop the container
docker-compose down

# View logs
docker-compose logs -f

# Restart after changes
docker-compose restart

# Rebuild after Dockerfile changes
docker-compose up -d --build

# Check container status
docker-compose ps

# Access container shell
docker exec -it jail-release-analyzer bash
```

## Troubleshooting

### Container won't start
```bash
# Check logs
docker-compose logs

# Remove old containers and rebuild
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

### Out of memory errors
- Increase memory limit in Dockerfile
- Reduce number of CSV files being processed
- Process files in smaller batches

### Data not loading
```bash
# Check if CSV files are in the data directory
ls -la data/

# Verify file permissions
docker exec -it jail-release-analyzer ls -la /var/www/html/data/

# Clear cache and retry
# Use "Clear Cache" button in the web interface
```

### Parse errors in index.php
- Ensure you're using the latest version of index.php from the artifacts
- Check for any manual edits that may have introduced syntax errors
- Rebuild container: `docker-compose up -d --build`

## Performance Notes

- **First Load**: 2-5 minutes for 400+ CSV files (~86,000 records)
- **Cached Loads**: Instant (< 1 second)
- **Search**: ~1-2 seconds for 100 results across all files
- **Memory Usage**: ~256MB typical, 512MB max

## CSV File Format

Expected CSV structure:
```csv
SONumber,InmateName,Address1,CaseNumber,Court,AttorneyName,ReleaseDate,ReleaseTime,ReleaseType,OffenseDescription,OffenseDate,BondType,BondAmount,BondsmanName
12345,John Doe,123 Main St,2024-CR-001,District Court,Jane Smith,10/02/2024,14:30,Bond,DWI,09/15/2024,Surety,5000.00,ABC Bonds
```

## Technology Stack

- **Backend**: PHP 8.2
- **Web Server**: Apache
- **Containerization**: Docker, Docker Compose
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Data Processing**: CSV streaming, in-memory caching

## Contributing

Contributions are welcome! Please feel free to submit issues or pull requests.

## License

MIT License

Copyright (c) 2025

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## Support

For issues, questions, or feature requests, please open an issue on the project repository.

## Acknowledgments

Built to analyze public jail release data for transparency and data-driven insights into the criminal justice system.

---

**Version**: 1.0.0  
**Last Updated**: October 2025